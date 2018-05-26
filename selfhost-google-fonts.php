<?php
/**
 * Self-Hosted Google Fonts
 * 
 * @package           Sphere\SGF
 *
 * Plugin Name:       Self-Hosted Google Fonts
 * Description:       Automatically self-host your Google Fonts - works with any theme or plugin.
 * Version:           1.0.0
 * Author:            asadkn
 * Author URI:        https://profiles.wordpress.org/asadkn/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       sphere-sgf
 * Domain Path:       /languages
 * Requires PHP:      5.3
 */

defined('WPINC') || exit;

if (version_compare( phpversion(), '5.3.2', '<')) {
		
	/**
	 * Display an admin error notice when PHP is older the version 5.3.2
	 * Hook it to the 'admin_notices' action.
	 */
	function sgf_old_php_admin_error_notice() {
		
		$message = sprintf(esc_html__(
			'The %2$sSelf-Hosted Google Fonts%3$s plugin requires %2$sPHP 5.3.2+%3$s to run properly. Please contact your web hosting company and ask them to update the PHP version of your site.%4$s Your current version of PHP: %2$s%1$s%3$s', 'sphere-sgf'), 
			phpversion(), 
			'<strong>', 
			'</strong>', 
			'<br>'
		);

		printf('<div class="notice notice-error"><p>%1$s</p></div>', wp_kses_post($message));
	}
	
	add_action('admin_notices', 'sgf_old_php_admin_error_notice');
	
	// bail
	return;
}

/**
 * Launch the plugin
 */
require_once 'inc/plugin.php';

$plugin = \Sphere\SGF\Plugin::get_instance();
$plugin->plugin_file = __FILE__;

// Init on plugins loaded
add_action('plugins_loaded', array($plugin, 'init'));

//$plugin->init();

/**
 * Register activation and deactivation hooks
 */

register_activation_hook(__FILE__, function() {
	// Noop
});

register_deactivation_hook(__FILE__, function() {
	// Noop
});