<?php

namespace Sphere\SGF;

/**
 * Filesystem that mainly wraps the WP_File_System
 * 
 * @author  asadkn
 * @since   1.0.0
 * @package Sphere\SGF
 */
class FileSystem {

	/**
	 * @var WP_Filesystem_Base
	 */
	public $filesystem;

	/**
	 * Setup file system
	 */
	public function __construct()
	{
		global $wp_filesystem;

		if (empty($wp_filesystem)) {

			require_once wp_normalize_path(ABSPATH . '/wp-admin/includes/file.php');
			
			// At shutdown is usually a ob_start callback which doesn't permit calling ob_*
			if (did_action('shutdown') && ob_get_level() > 0) {
				$creds = request_filesystem_credentials('');
			}
			else {
				ob_start();
				$creds = request_filesystem_credentials('');
				ob_end_clean();				
			}

			if (!$creds) {
				$creds = arrays();
			}

			$filesystem = WP_Filesystem($creds);
			
			if (!$filesystem) {
				
				// Fallback to lax permissions
				$upload = wp_upload_dir();
				WP_Filesystem(false, $upload['basedir'], true);
			}
		}

		$this->filesystem = $wp_filesystem;
	}

	/**
	 * Proxies to WP_Filesystem_Base
	 */
	public function __call($name, $arguments)
	{
		return call_user_func_array(array($this->filesystem, $name), $arguments);
	}

}