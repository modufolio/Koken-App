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
 * @copyright		Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @copyright		Copyright (c) 2014 - 2015, British Columbia Institute of Technology (http://bcit.ca/)
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Javascript Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Javascript
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/javascript.html
 */
class CI_Javascript
{
    public $_javascript_location = 'js';

    public function __construct($params = [])
    {
        $defaults = ['js_library_driver' => 'jquery', 'autoload' => true];

        foreach ($defaults as $key => $val) {
            if (isset($params[$key]) && $params[$key] !== "") {
                $defaults[$key] = $params[$key];
            }
        }

        extract($defaults);

        $this->CI =& get_instance();

        // load the requested js library
        $this->CI->load->library('javascript/'.$js_library_driver, ['autoload' => $autoload]);
        // make js to refer to current library
        $this->js =& $this->CI->$js_library_driver;

        log_message('debug', "Javascript Class Initialized and loaded.  Driver used: $js_library_driver");
    }

    // --------------------------------------------------------------------
    // Event Code
    // --------------------------------------------------------------------

    /**
     * Blur
     *
     * Outputs a javascript library blur event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function blur($element = 'this', $js = '')
    {
        return $this->js->_blur($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Change
     *
     * Outputs a javascript library change event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function change($element = 'this', $js = '')
    {
        return $this->js->_change($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Click
     *
     * Outputs a javascript library click event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @param	boolean	whether or not to return false
     * @return	string
     */
    public function click($element = 'this', $js = '', $ret_false = true)
    {
        return $this->js->_click($element, $js, $ret_false);
    }

    // --------------------------------------------------------------------

    /**
     * Double Click
     *
     * Outputs a javascript library dblclick event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function dblclick($element = 'this', $js = '')
    {
        return $this->js->_dblclick($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Error
     *
     * Outputs a javascript library error event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function error($element = 'this', $js = '')
    {
        return $this->js->_error($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Focus
     *
     * Outputs a javascript library focus event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function focus($element = 'this', $js = '')
    {
        return $this->js->__add_event($focus, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Hover
     *
     * Outputs a javascript library hover event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- Javascript code for mouse over
     * @param	string	- Javascript code for mouse out
     * @return	string
     */
    public function hover($over, $out, $element = 'this')
    {
        return $this->js->__hover($element, $over, $out);
    }

    // --------------------------------------------------------------------

    /**
     * Keydown
     *
     * Outputs a javascript library keydown event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function keydown($element = 'this', $js = '')
    {
        return $this->js->_keydown($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Keyup
     *
     * Outputs a javascript library keydown event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function keyup($element = 'this', $js = '')
    {
        return $this->js->_keyup($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Load
     *
     * Outputs a javascript library load event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function load($element = 'this', $js = '')
    {
        return $this->js->_load($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Mousedown
     *
     * Outputs a javascript library mousedown event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function mousedown($element = 'this', $js = '')
    {
        return $this->js->_mousedown($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Mouse Out
     *
     * Outputs a javascript library mouseout event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function mouseout($element = 'this', $js = '')
    {
        return $this->js->_mouseout($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Mouse Over
     *
     * Outputs a javascript library mouseover event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function mouseover($element = 'this', $js = '')
    {
        return $this->js->_mouseover($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Mouseup
     *
     * Outputs a javascript library mouseup event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function mouseup($element = 'this', $js = '')
    {
        return $this->js->_mouseup($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Output
     *
     * Outputs the called javascript to the screen
     *
     * @access	public
     * @param	string	The code to output
     * @return	string
     */
    public function output($js)
    {
        return $this->js->_output($js);
    }

    // --------------------------------------------------------------------

    /**
     * Ready
     *
     * Outputs a javascript library mouseup event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function ready($js)
    {
        return $this->js->_document_ready($js);
    }

    // --------------------------------------------------------------------

    /**
     * Resize
     *
     * Outputs a javascript library resize event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function resize($element = 'this', $js = '')
    {
        return $this->js->_resize($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Scroll
     *
     * Outputs a javascript library scroll event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function scroll($element = 'this', $js = '')
    {
        return $this->js->_scroll($element, $js);
    }

    // --------------------------------------------------------------------

    /**
     * Unload
     *
     * Outputs a javascript library unload event
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	string	The code to execute
     * @return	string
     */
    public function unload($element = 'this', $js = '')
    {
        return $this->js->_unload($element, $js);
    }

    // --------------------------------------------------------------------
    // Effects
    // --------------------------------------------------------------------


    /**
     * Add Class
     *
     * Outputs a javascript library addClass event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- Class to add
     * @return	string
     */
    public function addClass($element = 'this', $class = '')
    {
        return $this->js->_addClass($element, $class);
    }

    // --------------------------------------------------------------------

    /**
     * Animate
     *
     * Outputs a javascript library animate event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function animate($element = 'this', $params = [], $speed = '', $extra = '')
    {
        return $this->js->_animate($element, $params, $speed, $extra);
    }

    // --------------------------------------------------------------------

    /**
     * Fade In
     *
     * Outputs a javascript library hide event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function fadeIn($element = 'this', $speed = '', $callback = '')
    {
        return $this->js->_fadeIn($element, $speed, $callback);
    }

    // --------------------------------------------------------------------

    /**
     * Fade Out
     *
     * Outputs a javascript library hide event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function fadeOut($element = 'this', $speed = '', $callback = '')
    {
        return $this->js->_fadeOut($element, $speed, $callback);
    }
    // --------------------------------------------------------------------

    /**
     * Slide Up
     *
     * Outputs a javascript library slideUp event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function slideUp($element = 'this', $speed = '', $callback = '')
    {
        return $this->js->_slideUp($element, $speed, $callback);
    }

    // --------------------------------------------------------------------

    /**
     * Remove Class
     *
     * Outputs a javascript library removeClass event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- Class to add
     * @return	string
     */
    public function removeClass($element = 'this', $class = '')
    {
        return $this->js->_removeClass($element, $class);
    }

    // --------------------------------------------------------------------

    /**
     * Slide Down
     *
     * Outputs a javascript library slideDown event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function slideDown($element = 'this', $speed = '', $callback = '')
    {
        return $this->js->_slideDown($element, $speed, $callback);
    }

    // --------------------------------------------------------------------

    /**
     * Slide Toggle
     *
     * Outputs a javascript library slideToggle event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function slideToggle($element = 'this', $speed = '', $callback = '')
    {
        return $this->js->_slideToggle($element, $speed, $callback);
    }

    // --------------------------------------------------------------------

    /**
     * Hide
     *
     * Outputs a javascript library hide action
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function hide($element = 'this', $speed = '', $callback = '')
    {
        return $this->js->_hide($element, $speed, $callback);
    }

    // --------------------------------------------------------------------

    /**
     * Toggle
     *
     * Outputs a javascript library toggle event
     *
     * @access	public
     * @param	string	- element
     * @return	string
     */
    public function toggle($element = 'this')
    {
        return $this->js->_toggle($element);
    }

    // --------------------------------------------------------------------

    /**
     * Toggle Class
     *
     * Outputs a javascript library toggle class event
     *
     * @access	public
     * @param	string	- element
     * @return	string
     */
    public function toggleClass($element = 'this', $class='')
    {
        return $this->js->_toggleClass($element, $class);
    }

    // --------------------------------------------------------------------

    /**
     * Show
     *
     * Outputs a javascript library show event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function show($element = 'this', $speed = '', $callback = '')
    {
        return $this->js->_show($element, $speed, $callback);
    }


    // --------------------------------------------------------------------

    /**
     * Compile
     *
     * gather together all script needing to be output
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @return	string
     */
    public function compile($view_var = 'script_foot', $script_tags = true)
    {
        $this->js->_compile($view_var, $script_tags);
    }

    /**
     * Clear Compile
     *
     * Clears any previous javascript collected for output
     *
     * @access	public
     * @return	void
     */
    public function clear_compile()
    {
        $this->js->_clear_compile();
    }

    // --------------------------------------------------------------------

    /**
     * External
     *
     * Outputs a <script> tag with the source as an external js file
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @return	string
     */
    public function external($external_file = '', $relative = false)
    {
        if ($external_file !== '') {
            $this->_javascript_location = $external_file;
        } else {
            if ($this->CI->config->item('javascript_location') != '') {
                $this->_javascript_location = $this->CI->config->item('javascript_location');
            }
        }

        if ($relative === true or str_starts_with((string) $external_file, 'http://') or str_starts_with((string) $external_file, 'https://')) {
            $str = $this->_open_script($external_file);
        } elseif (str_contains((string) $this->_javascript_location, 'http://')) {
            $str = $this->_open_script($this->_javascript_location.$external_file);
        } else {
            $str = $this->_open_script($this->CI->config->slash_item('base_url').$this->_javascript_location.$external_file);
        }

        $str .= $this->_close_script();
        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Inline
     *
     * Outputs a <script> tag
     *
     * @access	public
     * @param	string	The element to attach the event to
     * @param	boolean	If a CDATA section should be added
     * @return	string
     */
    public function inline($script, $cdata = true)
    {
        $str = $this->_open_script();
        $str .= ($cdata) ? "\n// <![CDATA[\n{$script}\n// ]]>\n" : "\n{$script}\n";
        $str .= $this->_close_script();

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Open Script
     *
     * Outputs an opening <script>
     *
     * @access	private
     * @param	string
     * @return	string
     */
    public function _open_script($src = '')
    {
        $str = '<script type="text/javascript" charset="'.strtolower((string) $this->CI->config->item('charset')).'"';
        $str .= ($src == '') ? '>' : ' src="'.$src.'">';
        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Close Script
     *
     * Outputs an closing </script>
     *
     * @access	private
     * @param	string
     * @return	string
     */
    public function _close_script($extra = "\n")
    {
        return "</script>$extra";
    }


    // --------------------------------------------------------------------
    // --------------------------------------------------------------------
    // AJAX-Y STUFF - still a testbed
    // --------------------------------------------------------------------
    // --------------------------------------------------------------------

    /**
     * Update
     *
     * Outputs a javascript library slideDown event
     *
     * @access	public
     * @param	string	- element
     * @param	string	- One of 'slow', 'normal', 'fast', or time in milliseconds
     * @param	string	- Javascript callback function
     * @return	string
     */
    public function update($element = 'this', $speed = '', $callback = '')
    {
        return $this->js->_updater($element, $speed, $callback);
    }

    // --------------------------------------------------------------------

    /**
     * Generate JSON
     *
     * Can be passed a database result or associative array and returns a JSON formatted string
     *
     * @param	mixed	result set or array
     * @param	bool	match array types (defaults to objects)
     * @return	string	a json formatted string
     */
    public function generate_json($result = null, $match_array_type = false)
    {
        // JSON data can optionally be passed to this function
        // either as a database result object or an array, or a user supplied array
        if (! is_null($result)) {
            if (is_object($result)) {
                $json_result = $result->result_array();
            } elseif (is_array($result)) {
                $json_result = $result;
            } else {
                return $this->_prep_args($result);
            }
        } else {
            return 'null';
        }

        $json = [];
        $_is_assoc = true;

        if (! is_array($json_result) and empty($json_result)) {
            show_error("Generate JSON Failed - Illegal key, value pair.");
        } elseif ($match_array_type) {
            $_is_assoc = $this->_is_associative_array($json_result);
        }

        foreach ($json_result as $k => $v) {
            if ($_is_assoc) {
                $json[] = $this->_prep_args($k, true).':'.$this->generate_json($v, $match_array_type);
            } else {
                $json[] = $this->generate_json($v, $match_array_type);
            }
        }

        $json = implode(',', $json);

        return $_is_assoc ? "{".$json."}" : "[".$json."]";
    }

    // --------------------------------------------------------------------

    /**
     * Is associative array
     *
     * Checks for an associative array
     *
     * @access	public
     * @param	type
     * @return	type
     */
    public function _is_associative_array($arr)
    {
        foreach (array_keys($arr) as $key => $val) {
            if ($key !== $val) {
                return true;
            }
        }

        return false;
    }

    // --------------------------------------------------------------------

    /**
     * Prep Args
     *
     * Ensures a standard json value and escapes values
     *
     * @access	public
     * @param	type
     * @return	type
     */
    public function _prep_args($result, $is_key = false)
    {
        if (is_null($result)) {
            return 'null';
        } elseif (is_bool($result)) {
            return ($result === true) ? 'true' : 'false';
        } elseif (is_string($result) or $is_key) {
            return '"'.str_replace(['\\', "\t", "\n", "\r", '"', '/'], ['\\\\', '\\t', '\\n', "\\r", '\"', '\/'], $result).'"';
        } elseif (is_scalar($result)) {
            return $result;
        }
    }

    // --------------------------------------------------------------------
}
// END Javascript Class

/* End of file Javascript.php */
/* Location: ./system/libraries/Javascript.php */
