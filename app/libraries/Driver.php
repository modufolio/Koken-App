<?php

 if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright		Copyright (c) 2006 - 2014, EllisLab, Inc.
 * @copyright		Copyright (c) 2014 - 2015, British Columbia Institute of Technology (http://bcit.ca/)
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * CodeIgniter Driver Library Class
 *
 * This class enables you to create "Driver" libraries that add runtime ability
 * to extend the capabilities of a class via additional driver objects
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link
 */
class CI_Driver_Library
{
    protected $valid_drivers	= [];
    protected $lib_name;

    // The first time a child is used it won't exist, so we instantiate it
    // subsequents calls will go straight to the proper child.
    public function __get($child)
    {
        if (! isset($this->lib_name)) {
            $this->lib_name = static::class;
        }

        // The class will be prefixed with the parent lib
        $child_class = $this->lib_name.'_'.$child;

        // Remove the CI_ prefix and lowercase
        $lib_name = ucfirst(strtolower(str_replace('CI_', '', $this->lib_name)));
        $driver_name = strtolower(str_replace('CI_', '', $child_class));

        if (in_array($driver_name, array_map('strtolower', $this->valid_drivers))) {
            // check and see if the driver is in a separate file
            if (! class_exists($child_class)) {
                // check application path first
                foreach (get_instance()->load->get_package_paths(true) as $path) {
                    // loves me some nesting!
                    foreach ([ucfirst($driver_name), $driver_name] as $class) {
                        $filepath = $path.'libraries/'.$lib_name.'/drivers/'.$class.'.php';

                        if (file_exists($filepath)) {
                            include_once $filepath;
                            break;
                        }
                    }
                }

                // it's a valid driver, but the file simply can't be found
                if (! class_exists($child_class)) {
                    log_message('error', "Unable to load the requested driver: ".$child_class);
                    show_error("Unable to load the requested driver: ".$child_class);
                }
            }

            $obj = new $child_class();
            $obj->decorate($this);
            $this->$child = $obj;
            return $this->$child;
        }

        // The requested driver isn't valid!
        log_message('error', "Invalid driver requested: ".$child_class);
        show_error("Invalid driver requested: ".$child_class);
    }

    // --------------------------------------------------------------------
}
// END CI_Driver_Library CLASS


/**
 * CodeIgniter Driver Class
 *
 * This class enables you to create drivers for a Library based on the Driver Library.
 * It handles the drivers' access to the parent library
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link
 */
class CI_Driver
{
    protected $parent;

    private $methods = [];
    private $properties = [];

    private static $reflections = [];

    /**
     * Decorate
     *
     * Decorates the child with the parent driver lib's methods and properties
     *
     * @param	object
     * @return	void
     */
    public function decorate($parent)
    {
        $this->parent = $parent;

        // Lock down attributes to what is defined in the class
        // and speed up references in magic methods

        $class_name = $parent::class;

        if (! isset(self::$reflections[$class_name])) {
            $r = new ReflectionObject($parent);

            foreach ($r->getMethods() as $method) {
                if ($method->isPublic()) {
                    $this->methods[] = $method->getName();
                }
            }

            foreach ($r->getProperties() as $prop) {
                if ($prop->isPublic()) {
                    $this->properties[] = $prop->getName();
                }
            }

            self::$reflections[$class_name] = [$this->methods, $this->properties];
        } else {
            [$this->methods, $this->properties] = self::$reflections[$class_name];
        }
    }

    // --------------------------------------------------------------------

    /**
     * __call magic method
     *
     * Handles access to the parent driver library's methods
     *
     * @access	public
     * @param	string
     * @param	array
     * @return	mixed
     */
    public function __call($method, $args = [])
    {
        if (in_array($method, $this->methods)) {
            return call_user_func_array([$this->parent, $method], $args);
        }

        $trace = debug_backtrace();
        _exception_handler(E_ERROR, "No such method '{$method}'", $trace[1]['file'], $trace[1]['line']);
        exit;
    }

    // --------------------------------------------------------------------

    /**
     * __get magic method
     *
     * Handles reading of the parent driver library's properties
     *
     * @param	string
     * @return	mixed
     */
    public function __get($var)
    {
        if (in_array($var, $this->properties)) {
            return $this->parent->$var;
        }
    }

    // --------------------------------------------------------------------

    /**
     * __set magic method
     *
     * Handles writing to the parent driver library's properties
     *
     * @param	string
     * @param	array
     * @return	mixed
     */
    public function __set($var, $val)
    {
        if (in_array($var, $this->properties)) {
            $this->parent->$var = $val;
        }
    }
}
// END CI_Driver CLASS

/* End of file Driver.php */
/* Location: ./system/libraries/Driver.php */
