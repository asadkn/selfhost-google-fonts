<?php
namespace Sphere\SGF;

/**
 * Process, download and generate CSS for a Google Fonts
 * 
 * The base where all the magic begins. Processes enqueues, css files, and inline
 * CSS to find remote Google Fonts, download them and replace them with locally
 * hosted ones - depending on options.
 * 
 * @author  asadkn
 * @since   1.0.0
 * @package Sphere\SGF
 */
class Process
{
	const CSS_URLS_CACHE = 'sgf_css_urls_cache';

	/**
	 * JSON fonts file with all the Google Fonts data
	 */
	public $fonts_file;

	/**
	 * Internal ranges
	 */
	public $_json;
	public $_ranges;

	public function __construct()
	{
		// The fonts JSON file - compiled via Google API using a custom exhaustive method
		$this->fonts_file  = Plugin::get_instance()->dir_path . 'inc/data/google-fonts-src.json';
	}

	/**
	 * Setup filters for processing
	 */
	public function init()
	{
		if ($this->should_process()) {

			/**
			 * Process enqueues? Hook and return false to disable enqueues (overrides option)
			 */
			$enqueues = apply_filters('sgf/process_enqueues', Plugin::options()->process_enqueues);
			if ($enqueues) {
				add_filter('style_loader_src', array($this, 'process_enqueue'));
			}

			// Remove resource hints once since google fonts are removed
			add_filter('wp_resource_hints', array($this, 'remove_fonts_prefetch'));

			/**
			 * Process HTML for inline and local stylesheets
			 */
			$process_html = Plugin::options()->process_css_files || Plugin::options()->process_css_inline;
			if (apply_filters('sgf/process_html', $process_html)) {

				/**
				 * Scan HTML for enqueues - wp_ob_end_flush_all() will take care of flushing it
				 * 
				 * Note: Autoptimize starts at priority 2 so we use 3 to process BEFORE AO.
				 */
				add_action('template_redirect', function() {	
					ob_start(array(
						Plugin::process_css(), 'process_markup'
					));
				}, 3);


				// DEBUG: Devs if your output is disappearing - which you need for debugging,
				// uncomment below and comment the init action above.
				// add_action('init', function() { ob_start(); }, 100);
				// add_action('shutdown', function() {
				// 	$content = ob_get_clean();
				// 	echo Plugin::process_css()->process_markup($content);
				// }, -10);
			}

		}
	}

	/**
	 * Process standard WordPress enqueues
	 */
	public function process_enqueue($url)
	{
		if (stripos($url, 'fonts.googleapis.com/css') !== false) {
			return esc_url(
				$this->get_processed($url, array($this, 'process_fonts_url'))
			);
		}

		return $url;
	}

	/**
	 * Get a processed CSS file from the cache or use a generator function
	 * to process and cache a CSS file.
	 * 
	 * Use output argument to return CSS content instead of a URL.
	 * 
	 * @see  get_transient()
	 * @uses self::generate_css()
	 * @uses self::parse_fonts_url()
	 * 
	 * @param string   $url        A CSS file URL, can be a local or an gfonts URL
	 * @param callable $generator  Callback to run if cache isn't found
	 * @param string   $output     Return 'url' or CSS content?
	 * 
	 * @return string  URL or Google Fonts CSS with @font-face rules
	 */
	public function get_processed($url, $generator = null, $output = 'url')
	{
		$cache = $this->get_cache($url);

		if (!empty($cache)) {
			$file = $cache;
		}
		else {

			if (is_callable($generator)) {

				// Run the generator
				$file = call_user_func($generator, $url);

				// Update cache
				if (!empty($file)) {
					$this->add_cache($url, $file);
				}
			}
			else {
				$file = false;
			}
		}

		/**
		 * Return URL or CSS?
		 */
		if ($output == 'url') {
			if (!empty($file)) {
				return $this->get_upload_url($file);
			}

			return $url;
		}
		
		if ($output == 'css') {
			return Plugin::file_system()->get_contents(
				$this->get_upload_path($file)
			);
		}
	}

	/**
	 * Add a url to file name to cache
	 */
	public function add_cache($url, $file)
	{
		$cache = $this->get_cache();
		$cache[ md5($url) ] = $file;

		set_transient(self::CSS_URLS_CACHE, $cache);
	}

	/**
	 * Get cache for a URL or return all cache
	 */
	public function get_cache($url = null)
	{
		$cache = (array) get_transient(self::CSS_URLS_CACHE);

		if ($url === null) {
			return $cache;
		}

		$cache_key = md5($url);
		if (!empty($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		return false;
	}

	/**
	 * Process and generate Google Fonts' CSS by URL
	 * 
	 * @return string Local css file name 
	 */
	public function process_fonts_url($url)
	{
		$fonts = $this->parse_fonts_url($url);
		$css   = $this->generate_css($fonts['families'], $fonts['subsets']);

		// Empty? Can't be
		if (empty($css)) {
			return $url;
		}

		// Create CSS file
		$file = $this->create_css_file(
			implode("\n", $css), 
			'font-' . md5($url)
		);

		return $file;
	}

	/**
	 * Generate CSS provided a list of fonts and subsets
	 * 
	 * Example:
	 * <code>
	 * 	generate_css(
	 *      [
	 *        ['name' => 'Source Sans Pro', 'variants' => '400,400i,600'],
	 *        ['name' => 'Lato', 'variants' => '400italic,600']
	 *      ],
	 *      ['latin', 'latin-ext']
	 *  );
	 * </code>
	 * 
	 * @param array $families
	 * @param array $subsets
	 * 
	 * @return array Array of CSS strings to join and use
	 */
	public function generate_css($families, $subsets)
	{
		$css = array();

		/**
		 * Each family can have multiple variants/weights
		 */
		$json = $this->get_fonts_json();

		foreach ($families as $font) {
			
			$font_obj = new GoogleFont($font['name'], $json[$font['name']]);

			// Generate the CSS for this family
			$css = array_merge(
				$css,
				$font_obj->generate_css($font['variants'], $subsets)
			);
		}

		return $css;
	}

	/**
	 * Parse a standard Google Fonts /css URL
	 * 
	 * @see self::parse_fonts_query()
	 * @return array
	 */
	public function parse_fonts_url($url)
	{
		// Decode htmlentities like &amp; and encoded URL
		$url = urldecode(
			wp_specialchars_decode(trim($url))
		);

		// Protocol relative fails with parse_url
		if (substr($url, 0, 2) == '//') {
			$url = 'https:' . $url;
		}

		/**
		 * Parse URL to determine families and subsets
		 */
		$query = parse_url(
			$url,
			PHP_URL_QUERY
		);

		return $this->parse_fonts_query($query);
	}

	/**
	 * Parses a Google Fonts query string and returns an array
	 * of families and subsets used.
	 * 
	 * @param string|array $query  Query string or parsed query string
	 * 
	 * @return array 
	 */
	public function parse_fonts_query($query)
	{
		if (!is_array($query)) {
			parse_str($query, $parsed);
		}
		else {
			$parsed = $query;
		}

		if (empty($parsed['subset'])) {
			$parsed['subset'] = 'latin';
		}

		$families = explode('|', $parsed['family']);
		$subsets  = explode(',', $parsed['subset']);

		/**
		 * Parse variants/weights and font names
		 */
		foreach ($families as $k => $font) {

			$font_query = explode(':', $font);
			$font_name  = $font_query[0];

			if (!$font_query[1]) {
				$variants = array(400);
			}
			else {
				$variants = explode(',', $font_query[1]);
			}

			$families[$k] = array(
				'name'      => $font_name,
				'variants'  => $variants
			);
		}

		return array(
			'families' => $families,
			'subsets'  => $subsets
		);
	}

	/**
	 * Create CSS file and return the local URL
	 * 
	 * @param string|array  $css
	 * @param string|null   $name
	 */
	public function create_css_file($css, $name = '')
	{
		if (is_array($css)) {
			$css = implode("\n", $css);
		}

		if (!$name) {
			$name = md5(time() . rand(1, 100000));
		}
		
		$name .= '.css';

		$file  = $this->get_upload_path() . sanitize_file_name($name);
		Plugin::file_system()->put_contents($file, $css, FS_CHMOD_FILE);		

		return $name;
	}

	/**
	 * Google fonts prefetch is no longer needed
	 */
	public function remove_fonts_prefetch($urls)
	{
		return array_diff($urls, array('fonts.googleapis.com'));
	}

	/**
	 * Should any processing be done at all
	 */
	public function should_process()
	{
		if (is_admin()) {
			return false;
		}

		if (function_exists('is_customize_preview') && is_customize_preview()) {
			return false;
		}

		if (!Plugin::options()->enabled) {
			return false;
		}

		if (Plugin::options()->disable_for_admins && current_user_can('manage_options')) {
			return false;
		}

		return true;
	}

	/**
	 * Get upload path for fonts and CSS files
	 */
	public function get_upload_path($file = '')
	{
		$upload = wp_upload_dir(null, false);
		$path   = trailingslashit($upload['basedir']) . 'sgf-css/';

		if (!file_exists($path)) {
			Plugin::file_system()->mkdir($path, FS_CHMOD_DIR);
		}

		// Path for a specific file?
		if ($file && file_exists($path . $file)) {
			return wp_normalize_path($path . $file);
		}

		return $path;
	}

	/**
	 * Upload URL for fonts and CSS files
	 */
	public function get_upload_url($file = '')
	{
		$upload = wp_upload_dir(null, false);
		$path   = trailingslashit($upload['baseurl']) . 'sgf-css/';
		
		if ($file) {
			return $path . $file;
		}

		return $path;
	}

	/**
	 * Load and return the Google Fonts data
	 */
	public function get_fonts_json()
	{
		if (!$this->_json) {
			$json = json_decode(
				file_get_contents($this->fonts_file),
				true
			);

			$this->_json   = $json['fonts'];
			$this->_ranges = $json['ranges'];
		}

		return $this->_json;
	}
}