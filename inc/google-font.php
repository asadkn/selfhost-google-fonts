<?php

namespace Sphere\SGF;

/**
 * Functionality related to a single Google Font processing
 * 
 * @author  asadkn
 * @since   1.0.0
 * @package Sphere\SGF
 */
class GoogleFont
{
	public $name;

	/**
	 * Font data collected from API - via JSON for this font 
	 */
	public $font_data;

	public function __construct($name, $data = array())
	{
		$this->name = $name;
		$this->font_data = $data;
	}

	/**
	 * Generate CSS based on variants and subsets
	 */
	public function generate_css($variants, $subsets)
	{
		$css = array();

		foreach ($subsets as $subset) {

			foreach ($variants as $variant) {
				
				$italic = false;

				// Remove italics in variant
				if (strpos($variant, 'i') !== false) {
					$italic  = true;
					$variant = (int) str_replace(array('i', 'italic', 'italics'), '', $variant);
				}

				// Variant doesn't exist?
				if (empty($this->font_data[$subset][$variant])) {
					continue;
				}
				
				// Font data (from JSON)
				$data = $this->font_data[$subset][$variant];

				$data['fontFile']     = $this->download_font($data['fontFile']);
				$data['fontFileWoff'] = $this->download_font($data['fontFileWoff']);

				if (!$data['fontFile'] || !$data['fontFileWoff']) {
					//continue;
				}

				// Common CSS rules to create
				$rules = array(
					'font-family: "' . sanitize_text_field($this->name) .'"',
					'font-weight: ' . intval($variant),
					'font-style: '  . ($italic ? 'italic' : 'normal')
				);

				/**
				 * Build src array with localNames first and woff/woff2 next
				 */
				$src = array();
				foreach ((array) $data['localNames'] as $local) {
					$src[] = "local('{$local}')";
				}

				$src[] = 'url(' . esc_url_raw($data['fontFile']) . ") format('woff2')";
				$src[] = 'url(' . esc_url_raw($data['fontFileWoff']) . ") format('woff')";

				// Add to rules array
				$rules[] = 'src: ' . implode(', ', $src);

				if (($range = $this->get_unicode_range($subset))) {
					$rules[] = 'unicode-range: ' . $range;
				}

				// Add some formatting
				$rules = array_map(function($rule) {
					return "\t" . $rule . ";";
				}, $rules);
				
				// Add to final CSS
				$css[]  = "@font-face {\n" . implode("\n", $rules) . "\n}";
			}
		}

		return $css;
	}

	/**
	 * Download google fonts to local filesystem
	 * 
	 * @uses Process::get_upload_path()
	 * @uses Process::get_upload_url()
	 * @uses FileSystem
	 * 
	 * @see wp_remote_get()
	 * 
	 * @return boolean
	 */
	public function download_font($url)
	{
		// Setup the file name
		$name = sanitize_file_name(basename($url));
		$file = Plugin::process()->get_upload_path() . $name;

		if (!file_exists($file)) {

			$request = wp_remote_get($url, array('sslverify' => false));
			if (is_wp_error($request)) {
				return false;
			}
	
			// DEBUG: echo "Downloading {$url}\n";

			Plugin::file_system()->put_contents(
				$file, 
				wp_remote_retrieve_body($request), 
				FS_CHMOD_FILE
			);
		}

		return Plugin::process()->get_upload_url() . $name;
	}

	/**
	 * Get unicode range for this font
	 * 
	 * @uses Process::$_ranges
	 */
	public function get_unicode_range($subset)
	{
		$ranges = Plugin::process()->_ranges;
		if (isset($ranges[$subset])) {
			return $ranges[$subset];
		}

		return false;
	}
}