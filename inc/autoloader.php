<?php

namespace Sphere\SGF;

/**
 * Custom Autoloader - does not follow any PSR
 * 
 * @author  asadkn
 * @since   1.0.0
 * @package Sphere\SGF
 */
class Autoloader 
{
	public $class_map = array();

	public function __construct()
	{
		spl_autoload_register(array($this, 'load'));
	}

	/**
	 * Autoloader the class either using a class map or via conversion of 
	 * class name to file.
	 * 
	 * @param string $class
	 */
	public function load($class) 
	{
		if (isset($this->class_map[$class])) {
			$file = $this->class_map[$class];
			
		}
		else {
			
			$namespaces = array(
				'Sphere\SGF\\'  => 'inc', 
			);

			foreach ($namespaces as $namespace => $dir) {
				if (strpos($class, $namespace) !== false) {
					$file = $this->get_file_path($class, $namespace, $dir);
					break;
				}
			}
		}

		if (!empty($file)) {
			require_once $file;
		}
	}

	/**
	 * Get file path to include
	 * 
	 * Examples:
	 *  Sphere\SGF\FooBar to inc/foo-bar.php
	 *  Sphere\SGF\Foo to inc/foo.php
	 * 
	 * @return string  Path to the file from the plugin dir
	 */
	public function get_file_path($class, $prefix = '', $path = '')
	{
		// Remove namespace and convert underscore as a namespace delim
		$class = str_replace($prefix, '', $class);
		
		// Split to convert CamelCase
		$parts = explode('\\', $class);
		foreach ($parts as $key => $part) {
			
			$test = substr($part, 1); 
					
			// Convert CamelCase to Camel-Case
			if (strtolower($test) !== $test) {
				$part = preg_replace('/(.)(?=[A-Z])/u', '$1-', $part);
			}

			$parts[$key] = $part;
		}

		$name = strtolower(array_pop($parts));
		$path = $path . '/' . strtolower(
			implode('/', $parts)
		);
		$path = trailingslashit($path);

		// Preferred and fallback file path
		$file  = Plugin::get_instance()->dir_path . $path . "{$name}.php";

		// Try with directory path pattern first
		if (file_exists($file)) {
			return $file;
		}
	}
}