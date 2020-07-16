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
	const PROCESSED_CACHE = 'sgf_processed_cache';
	const PRELOAD_CACHE   = 'sgf_preload_cache';

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
			$process_html = Plugin::options()->process_css_files || Plugin::options()->process_css_inline || Plugin::options()->process_wf_loader;
			if (apply_filters('sgf/process_html', $process_html)) {

				/**
				 * Scan HTML for enqueues - wp_ob_end_flush_all() will take care of flushing it
				 * 
				 * Note: Autoptimize starts at priority 2 so we use 3 to process BEFORE AO.
				 */
				add_action('template_redirect', function() {	
					
					ob_start(array($this, 'process_markup'));

				}, 3);

				// DEBUG: Devs if your output is disappearing - which you need for debugging,
				// uncomment below and comment the init action above.
				// add_action('init', function() { ob_start(); }, 100);
				// add_action('shutdown', function() {
				// 	$content = ob_get_clean();
				// 	echo $this->process_markup($content);
				// }, -10);
			}

			if (Plugin::options()->preload_fonts) {
				add_action('wp_print_styles', array($this, 'print_preload'));
			}
		}
	}

	/**
	 * Process standard WordPress enqueues
	 * 
	 * @param string $url
	 * @return string
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
	 * Process Markup as as needed
	 * 
	 * @param  string $html
	 * @return string
	 */
	public function process_markup($html)
	{
		$html = Plugin::process_css()->process_markup($html);

		// Process WebFont loader
		if (SGF_IS_PRO && Plugin::options()->process_wf_loader) {
			$html = Plugin::process_js()->process_markup($html);
		}
		
		return $html;
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
	 * 
	 * @param string $id     A URL or other unique id
	 * @param string $value  File or other value to store
	 */
	public function add_cache($id, $value)
	{
		$cache = $this->get_cache();
		$cache[ md5($id) ] = $value;

		set_transient(self::PROCESSED_CACHE, $cache);
	}

	/**
	 * Get cache for a URL or return all cache
	 * 
	 * @param string $id  A URL or other unique id
	 */
	public function get_cache($id = null)
	{
		$cache = (array) get_transient(self::PROCESSED_CACHE);

		if ($id === null) {
			return $cache;
		}

		$cache_key = md5($id);
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
	 * NOTE: Data must NOT be urlencoded. 
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

		$parsed   = array_map('trim', $parsed);
		$families = explode('|', $parsed['family']);

		$subsets = array();
		if (!empty($parsed['subset'])) {
			$subsets  = explode(',', $parsed['subset']);
		}

		/**
		 * Parse variants/weights and font names
		 */
		foreach ($families as $k => $font) {

			$font_query = explode(':', $font);
			$font_name  = trim($font_query[0]);

			if (empty($font_query[1])) {
				$variants = array(400);
			}
			else {
				$variants = explode(',', $font_query[1]);
			}

			$families[$k] = array(
				'name'      => $font_name,
				'variants'  => array_map('strtolower', $variants)
			);

			// Third chunk - probably a subset here from WF loader
			if (!empty($font_query[2])) {

				// Split and trim
				$font_subs = array_map('trim', explode(',', $font_query[2]));

				// Add it to the subsets array
				$subsets = array_merge($subsets, $font_subs);
			}
		}

		// Add fored subsets
		if (Plugin::options()->force_subsets) {
			$subsets = array_merge($subsets, (array) Plugin::options()->force_subsets);
		}

		// Remove duplicates
		$subsets = array_unique($subsets);

		// At least one subset is required
		if (empty($subsets)) {
			$subsets = array('latin');
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
	 * Output the preload for fonts
	 */
	public function print_preload()
	{
		$files = $this->get_preload_fonts();

		foreach ($files as $file) {
			echo '<link rel="preload" href="' . esc_url($file) . '" as="font">';
		}
	}

	/**
	 * Get all the font file URLs to preload
	 * 
	 * @see  get_transient()
	 * @see  set_transient()
	 * @uses self::get_fonts_json()
	 * 
	 * @return array  URLs of font files
	 */
	public function get_preload_fonts()
	{
		// Check it cache first?
		$cache = get_transient(self::PRELOAD_CACHE);
		if ($cache) {
			return $cache;
		}

		$fonts = (array) Plugin::options()->preload_fonts;
		$json  = $this->get_fonts_json();
		$files = array();

		foreach ($fonts as $font) {
			$font = explode(':', $font);
			$name = $font[0];

			// 2nd and 3rd chunks are weight and subset
			$weight = !empty($font[1]) ? trim($font[1]) : '400';
			$subset = !empty($font[2]) ? trim($font[2]) : 'latin';

			// Unknown font/weight/subset combo?
			if (empty($json[$name][$subset][$weight])) {
				continue;
			}

			// The woff2 file
			$file = $json[$name][$subset][$weight]['fontFile'];

			// Get local URL from cache if it was ever processed
			$cache = $this->get_cache($file);
			if ($cache) {
				$files[] = $cache;
			}
		}
		
		set_transient(self::PRELOAD_CACHE, $files);
		return $files;
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
		$url    = trailingslashit($upload['baseurl']) . 'sgf-css/';

		// Protocol relative URLs?
		if (Plugin::options()->protocol_relative) {
			$url = preg_replace('#^https?://#i', '//', $url);
		}

		if ($file) {
			return $url . $file;
		}

		return $url;
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