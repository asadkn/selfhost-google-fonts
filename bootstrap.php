<?php
/**
 * Plugin bootstrap
 * 
 * @since   1.0.1
 */

defined('WPINC') || exit;

if (version_compare(phpversion(), '5.4', '<')) {
		
	/**
	 * Display an admin error notice when PHP is older the version 5.4
	 * Hook it to the 'admin_notices' action.
	 */
	function sgf_old_php_admin_error_notice() {
		
		$message = sprintf(esc_html__(
			'The %2$sSelf-Hosted Google Fonts%3$s plugin requires %2$sPHP 5.4+%3$s to run properly. Please contact your web hosting company and ask them to update the PHP version of your site.%4$s Your current version of PHP has reached end-of-life is %2$shighly insecure: %1$s%3$s', 'sphere-sgf'), 
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

// Already exists? Probably the other version
if (class_exists('\Sphere\SGF\Plugin', false)) {

	add_action('admin_notices', function() {
		$message = 'Self-Hosted Google Fonts and Self-Hosted Google Fonts Pro cannot be active at the same time. Please disable the non-pro.';
		printf('<div class="notice notice-error"><p>%1$s</p></div>', wp_kses_post($message));
	});

	return;
}

/**
 * Launch the plugin
 */
require_once plugin_dir_path(__FILE__) . 'inc/plugin.php';

$plugin = \Sphere\SGF\Plugin::get_instance();
$plugin->plugin_file = __FILE__;

// Init on plugins loaded
add_action('plugins_loaded', array($plugin, 'init'));

/**
 * Register activation and deactivation hooks
 */

register_activation_hook(__FILE__, function() {
	// Noop
});

register_deactivation_hook(__FILE__, function() {
	// Noop
});