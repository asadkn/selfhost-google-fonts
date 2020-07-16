<?php

namespace Sphere\SGF;

/**
 * The Plugin Bootstrap and Setup
 * 
 * Dual acts as a container and a facade.
 * 
 * @author  asadkn
 * @since   1.0.0
 * @package Sphere\SGF
 * 
 * @method static \WP_Filesystem_Base  file_system()  Via \Sphere\SGF\Filesystem
 * @method static Process    process()
 * @method static ProcessCss process_css()
 * @method static ProcessJs  process_js()
 * @method static Options    options()
 */
class Plugin 
{
	/**
	 * Plugin version
	 */
	const VERSION = '1.0.1';

	public static $instance;

	public $dir_path;
	public $dir_url;
	public $plugin_file;

	/**
	 * A pseudo service container
	 */
	public $container;

	/**
	 * Set it hooks on init
	 */
	public function init() 
	{
		$this->dir_path = plugin_dir_path($this->plugin_file);
		$this->dir_url  = plugin_dir_url($this->plugin_file);

		// Fire up the autoloader
		require_once $this->dir_path . 'inc/autoloader.php';
		new Autoloader;

		/**
		 * Setup and init common requires
		 */

		$this->container['process'] = new Process;
		
		// File system with lazy init singleton
		$this->container['file_system'] = $this->shared('\Sphere\SGF\FileSystem');

		// Process CSS with lazy init singleton
		$this->container['process_css'] = $this->shared('\Sphere\SGF\ProcessCss');

		// Process JS - PRO only
		$this->container['process_js']  = $this->shared('\Sphere\SGF\ProcessJs');

		// Options object with bound constructor args
		$this->container['options'] = $this->shared('\Sphere\SGF\Options', array('sgf_options'));

		if (SGF_IS_PRO) {
			
			// Verification object
			$this->container['verify'] = new Verify;
			self::verify()->init();
		}
		
		/**
		 * Admin only requires
		 * 
		 * We load these only on Admin side to keep things lean and performant.
		 * 
		 * Note on CMB2: 
		 *  It's used ONLY as an admin side dependency and never even
		 *  loaded on the frontend. Use native WP options API on front.
		 */
		if (is_admin()) {

			$admin = new Admin;
			$admin->init();

			if (SGF_IS_PRO) {
				$admin_pro = new AdminPro;
				$admin_pro->init();
			}

			// We don't want CMB2 backend loaded at all in AJAX requests (admin-ajax.php passes is_admin() test)
			if (!wp_doing_ajax()) {
				require_once $this->dir_path . 'vendor/cmb2/init.php';
			}

			// Path bug fix for cmb2 in composer
			add_filter('cmb2_meta_box_url', function() {
				return self::get_instance()->dir_url . 'vendor/cmb2';
			});
		}

		$this->register_hooks();

		// Init process to setup hooks
		self::process()->init();
	}

	/**
	 * Creates a single instance class for container
	 * 
	 * @param string     $class  Fully-qualifed class name
	 * @param array|null $args   Bound args to pass to constructor
	 */
	public function shared($class, $args = null) 
	{
		return function($fresh = false) use ($class, $args) {
			static $object;

			if (!$object || $fresh) {

				if (!$args) {
					$object = new $class;
				}
				else {
					$ref = new \ReflectionClass($class);
					$object = $ref->newInstanceArgs($args);
				}
			}

			return $object;
		};
	}

	/**
	 * Setup hooks actions
	 */
	public function register_hooks()
	{
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));

		// Translations
		add_action('plugins_loaded', array($this, 'load_textdomain'));
	}

	/**
	 * Register the JS and CSS
	 */
	public function register_assets()
	{
	}

	/**
	 * Setup translations
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain(
			'selfhost-google-fonts',
			false,
			$this->dir_path . '/languages/'
		);
	}

	/**
	 * Gets an object from container 
	 */
	public function get($name, $args = array()) 
	{
		$object = $this->container[$name];

		if (is_callable($object)) {
			return call_user_func_array($object, $args);
		}
		else if (is_string($object)) {
			$object = new $object;
		}

		return $object;
	}

	/**
	 * @uses self::get()
	 */
	public static function __callStatic($name, $args = array())
	{
		return self::get_instance()->get($name, $args);
	}

	/**
	 * @return \Sphere\SGF\Plugin
	 */
	public static function get_instance()
	{
		if (self::$instance == null) {
			self::$instance = new static();
		}

		return self::$instance;
	}
}