<?php
/**
 * Self-Hosted Google Fonts
 * 
 * @package           Sphere\SGF
 *
 * Plugin Name:       Self-Hosted Google Fonts
 * Description:       Automatically self-host your Google Fonts - works with any theme or plugin.
 * Version:           1.0.1
 * Author:            asadkn
 * Author URI:        https://profiles.wordpress.org/asadkn/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       sphere-sgf
 * Domain Path:       /languages
 * Requires PHP:      5.4
 */

defined('WPINC') || exit;

// Not so easy. Setting this to true on free version will give FATAL errors as some 
// files are only in pro version. No cheating.
define('SGF_IS_PRO', false);

require_once plugin_dir_path(__FILE__) . 'bootstrap.php';