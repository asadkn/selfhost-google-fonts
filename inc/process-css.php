<?php

namespace Sphere\SGF;

/**
 * Process raw CSS and stylesheet files for Google fonts
 * 
 * @author  asadkn
 * @since   1.0.0
 * @package Sphere\SGF
 */
class ProcessCss {

	/**
	 * Pattern to remove protocol and www
	 */
	const PROTO_REMOVE_PATTERN = '#^(https?)?:?//(|www\.)#i';

	public $valid_hosts;
	public $paths_urls;

	/**
	 * Process all the HTML to find CSS files
	 */
	public function process_markup($html)
	{

		$process_sheets = apply_filters('sgf/process_css_files', Plugin::options()->process_css_files);
		if ($process_sheets) {
			$html = $this->_markup_stylesheets($html);
		}

		$process_inline = apply_filters('sgf/process_css_inline', Plugin::options()->process_css_inline);
		if ($process_inline) {
			$html = $this->_markup_inline_css($html);
		}
		

		return $html;
	}

	/**
	 * Process stylesheets in the HTML
	 * 
	 * @uses self::process_file_by_url()
	 */
	public function _markup_stylesheets($html) 
	{
		// Process stylesheets
		preg_match_all('#(<link[^>]*(stylesheet|as=.?style)[^>]*>)#Usi', $html, $sheets);
		
		foreach ($sheets[0] as $sheet) {
			
			$href = preg_match('#<link[^>]*href=("|\'|)(.*)("|\'|\s)#Ui', $sheet, $url);

			if (!empty($url[2])) {
				
				// Google Fonts here without using WP enqueues?
				if (stripos($url[2], 'fonts.googleapis.com/css') !== false) {
					$generator = array(Plugin::process(), 'process_fonts_url');
				}
				else {

					// Perhaps a local CSS embed (will be ignored if not)
					$generator = array($this, 'process_file_by_url');
				}
				
				// Get from cache or use our generator
				$replace_url = Plugin::process()->get_processed(
					$url[2], $generator
				);

				if ($replace_url) {

					// Replace the link with locally saved CSS file
					$html = str_replace(
						$url[0],
						str_replace($url[2], esc_url($replace_url), $url[0]),
						$html
					);
				}
			}
		}

		return $html;
	}

	/**
	 * Process inline styles in HTML
	 * 
	 * @uses self::process_imports()
	 */
	public function _markup_inline_css($html)
	{
		// Process inline styles
		preg_match_all('#(<style[^>]*>.*</style>)#Usi', $html, $styles);

		foreach ($styles[0] as $style) {

			// Process imports and inline them
			$new_css = $this->process_imports($style);

			if ($new_css) {
				$html = str_replace($style, $new_css, $html);
			}
		}

		return $html;
	}

	/**
	 * Process a stylesheet via URL
	 * 
	 * @uses FileSystem
	 */
	public function process_file_by_url($url)
	{
		// Don't double process enqueues
		if (strpos($url, '/sgf-css/font-') !== false) {
			return false;
		}

		// URL likely to be encoded and have html entities
		$url = urldecode(
			wp_specialchars_decode(trim($url))
		);

		// Try to get local path for this stylesheet
		$file = $this->get_css_file_path($url);
		if (!$file) {
			return false;
		}

		$content = Plugin::file_system()->get_contents($file);
		if (!$content) {
			return false;
		}

		// Process imports and inline them
		$create_file = $this->process_imports($content);
		if ($create_file) {

			// Create CSS file
			$file = Plugin::process()->create_css_file(
				$create_file,
				'style-' . md5($url)
			);

			return $file;
		}

		return false;
	}

	/**
	 * Make @imports inline
	 * 
	 * @uses Process::get_processed()
	 * 
	 * @return bool|string  Returns false if no changes are needed or the updated content
	 */
	public function process_imports($content)
	{
		$changed = false;

		// Check for Google Font imports - benchmarked regex
		preg_match_all('#@import\s+url\( ([\"\']?|\s+) ([^"\'\s]*) (?:\\1|\s+) \)[^;]*;#six', $content, $imports);

		foreach ($imports[2] as $key => $import) {

			if (stripos($import, 'fonts.googleapis.com/css') === false) {
				continue;
			}

			// Get google fonts CSS - from cache or otherwise
			$css = Plugin::process()->get_processed(
				$import, 
				array(Plugin::process(), 'process_fonts_url'), 
				'css'
			);

			if (!empty($css)) {

				$content = str_replace(
					$imports[0][$key], 
					$css, 
					$content
				);

				$changed = true;
			}
		}

		if ($changed) {
			return $content;
		}

		return false;
	}

	/**
	 * Get a local file by the provided URL - CSS files only
	 * 
	 * @see wp_normalize_path()
	 */
	public function get_css_file_path($url)
	{
		$url = explode('?', trim($url));
		$url = trim($url[0]);

		// We can only support .css files yet
		if (substr($url, -4) != '.css') {
			return false;
		}

		// We're not working with encoded URLs
		if (strpos($url,'%') !== false) {
            $url = urldecode($url);
		}

		// Add http:// back for parse_url() or it fails
		$url_no_proto = preg_replace(self::PROTO_REMOVE_PATTERN, '', $url);
		$url_host     = parse_url('http://' . $url_no_proto, PHP_URL_HOST);

		// Not a known host / URL
		if (!in_array($url_host, $this->get_valid_hosts())) {
			return false;
		}

		/**
		 * Go through each known path url map and stop at first matched
		 */
		$valid_urls   = $this->get_paths_urls();
		$url_dirname  = dirname($url_no_proto);
		$matched      = array();

		foreach ($valid_urls as $path_url) {

			if (strpos($url_dirname, untrailingslashit($path_url['url'])) !== false) {
				$matched = $path_url;
				break;
			}
		}

		// We have a matched path
		if (!empty($matched['path'])) {
			$path = wp_normalize_path(
				$matched['path'] . str_replace($matched['url'], '', $url_dirname)
			);

			$file = trailingslashit($path) . wp_basename($url_no_proto);

			if (file_exists($file) && is_file($file) && is_readable($file)) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * Get recognized hostnames for stylesheet URLs
	 */
	public function get_valid_hosts()
	{
		if (!$this->valid_hosts) {
			$this->valid_hosts = wp_list_pluck(
				$this->get_paths_urls(),
				'host'
			);
		}

		return $this->valid_hosts;
	}

	/**
	 * Get a map of known path URLs, associated local path, and host
	 */
	public function get_paths_urls()
	{
		if (!$this->paths_urls) {

			// We add http:// back for parse_url() to prevent it from failing
			$site_url  = preg_replace(self::PROTO_REMOVE_PATTERN, '', site_url());
			$site_host = parse_url('http://' . $site_url, PHP_URL_HOST);

			$content_url  = preg_replace(self::PROTO_REMOVE_PATTERN, '', content_url());
			$content_host = parse_url('http://' . $content_url, PHP_URL_HOST);

			/**
			 * This array will be processed in order it's defined to find the matching host and URL.
			 * 
			 * @see self::process_style_url()
			 */
			$hosts = array(

				// First priority to use content_host and content_url()
				'content' => array(
					'url'  => $content_url, 
					'path' => WP_CONTENT_DIR,
					'host' => $content_host
				),
				
				// Fallback to using site URL with ABSPATH
				'style' => array(
					'url'  => $site_url, 
					'path' => ABSPATH,
					'host' => $site_host
				),
			);

			$this->paths_urls  = apply_filters('sgf/process_css/paths_urls', $hosts);
		}

		return $this->paths_urls;
	}
}