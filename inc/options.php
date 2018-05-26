<?php

namespace Sphere\SGF;

/**
 * A very basic options class
 * 
 * @author  asadkn
 * @since   1.0.0
 * @package Sphere\SGF
 */
class Options
{
	/**
	 * Option key to use for get_options()
	 */
	public $option_key;

	public $_options;

	public function __construct($option_key = '')
	{
		$this->option_key = $option_key;
	}

	/**
	 * Initialize
	 */
	public function init()
	{
		$this->_options = (array) get_option($this->option_key);
	}

	/**
	 * Get an option
	 */
	public function get($key, $fallback = '')
	{
		if (!$this->_options) {
			$this->init();
		}

		if (array_key_exists($key, $this->_options)) {
			return $this->_options[$key];
		}

		return $fallback;
	}

	public function __get($key)
	{
		return $this->get($key);
	}
}