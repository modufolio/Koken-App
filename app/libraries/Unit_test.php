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
 * @since		Version 1.3.1
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Unit Testing Class
 *
 * Simple testing class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	UnitTesting
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/uri.html
 */
class CI_Unit_test
{
    public $active					= true;
    public $results				= [];
    public $strict					= false;
    public $_template				= null;
    public $_template_rows			= null;
    public $_test_items_visible	= [];

    public function __construct()
    {
        // These are the default items visible when a test is run.
        $this->_test_items_visible = ['test_name', 'test_datatype', 'res_datatype', 'result', 'file', 'line', 'notes'];

        log_message('debug', "Unit Testing Class Initialized");
    }

    // --------------------------------------------------------------------

    /**
     * Run the tests
     *
     * Runs the supplied tests
     *
     * @access	public
     * @param	array
     * @return	void
     */
    public function set_test_items($items = [])
    {
        if (! empty($items) and is_array($items)) {
            $this->_test_items_visible = $items;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Run the tests
     *
     * Runs the supplied tests
     *
     * @access	public
     * @param	mixed
     * @param	mixed
     * @param	string
     * @return	string
     */
    public function run($test, $expected = true, $test_name = 'undefined', $notes = '')
    {
        if ($this->active == false) {
            return false;
        }

        if (in_array($expected, ['is_object', 'is_string', 'is_bool', 'is_true', 'is_false', 'is_int', 'is_numeric', 'is_float', 'is_double', 'is_array', 'is_null'], true)) {
            $expected = str_replace('is_float', 'is_double', $expected);
            $result = ($expected($test)) ? true : false;
            $extype = str_replace(['true', 'false'], 'bool', str_replace('is_', '', $expected));
        } else {
            if ($this->strict == true) {
                $result = ($test === $expected) ? true : false;
            } else {
                $result = ($test == $expected) ? true : false;
            }

            $extype = gettype($expected);
        }

        $back = $this->_backtrace();

        $report[] = ['test_name'			=> $test_name, 'test_datatype'		=> gettype($test), 'res_datatype'		=> $extype, 'result'			=> ($result === true) ? 'passed' : 'failed', 'file'				=> $back['file'], 'line'				=> $back['line'], 'notes'				=> $notes];

        $this->results[] = $report;

        return($this->report($this->result($report)));
    }

    // --------------------------------------------------------------------

    /**
     * Generate a report
     *
     * Displays a table with the test data
     *
     * @access	public
     * @return	string
     */
    public function report($result = [])
    {
        if (count($result) == 0) {
            $result = $this->result();
        }

        $CI =& get_instance();
        $CI->load->language('unit_test');

        $this->_parse_template();

        $r = '';
        foreach ($result as $res) {
            $table = '';

            foreach ($res as $key => $val) {
                if ($key == $CI->lang->line('ut_result')) {
                    if ($val == $CI->lang->line('ut_passed')) {
                        $val = '<span style="color: #0C0;">'.$val.'</span>';
                    } elseif ($val == $CI->lang->line('ut_failed')) {
                        $val = '<span style="color: #C00;">'.$val.'</span>';
                    }
                }

                $temp = $this->_template_rows;
                $temp = str_replace('{item}', $key, $temp);
                $temp = str_replace('{result}', $val, $temp);
                $table .= $temp;
            }

            $r .= str_replace('{rows}', $table, $this->_template);
        }

        return $r;
    }

    // --------------------------------------------------------------------

    /**
     * Use strict comparison
     *
     * Causes the evaluation to use === rather than ==
     *
     * @access	public
     * @param	bool
     * @return	null
     */
    public function use_strict($state = true)
    {
        $this->strict = ($state == false) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Make Unit testing active
     *
     * Enables/disables unit testing
     *
     * @access	public
     * @param	bool
     * @return	null
     */
    public function active($state = true)
    {
        $this->active = ($state == false) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Result Array
     *
     * Returns the raw result data
     *
     * @access	public
     * @return	array
     */
    public function result($results = [])
    {
        $CI =& get_instance();
        $CI->load->language('unit_test');

        if (count($results) == 0) {
            $results = $this->results;
        }

        $retval = [];
        foreach ($results as $result) {
            $temp = [];
            foreach ($result as $key => $val) {
                if (! in_array($key, $this->_test_items_visible)) {
                    continue;
                }

                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        if (false !== ($line = $CI->lang->line(strtolower('ut_'.$v)))) {
                            $v = $line;
                        }
                        $temp[$CI->lang->line('ut_'.$k)] = $v;
                    }
                } else {
                    if (false !== ($line = $CI->lang->line(strtolower('ut_'.$val)))) {
                        $val = $line;
                    }
                    $temp[$CI->lang->line('ut_'.$key)] = $val;
                }
            }

            $retval[] = $temp;
        }

        return $retval;
    }

    // --------------------------------------------------------------------

    /**
     * Set the template
     *
     * This lets us set the template to be used to display results
     *
     * @access	public
     * @param	string
     * @return	void
     */
    public function set_template($template)
    {
        $this->_template = $template;
    }

    // --------------------------------------------------------------------

    /**
     * Generate a backtrace
     *
     * This lets us show file names and line numbers
     *
     * @access	private
     * @return	array
     */
    public function _backtrace()
    {
        if (function_exists('debug_backtrace')) {
            $back = debug_backtrace();

            $file = (! isset($back['1']['file'])) ? '' : $back['1']['file'];
            $line = (! isset($back['1']['line'])) ? '' : $back['1']['line'];

            return ['file' => $file, 'line' => $line];
        }
        return ['file' => 'Unknown', 'line' => 'Unknown'];
    }

    // --------------------------------------------------------------------

    /**
     * Get Default Template
     *
     * @access	private
     * @return	string
     */
    public function _default_template()
    {
        $this->_template = "\n".'<table style="width:100%; font-size:small; margin:10px 0; border-collapse:collapse; border:1px solid #CCC;">';
        $this->_template .= '{rows}';
        $this->_template .= "\n".'</table>';

        $this->_template_rows = "\n\t".'<tr>';
        $this->_template_rows .= "\n\t\t".'<th style="text-align: left; border-bottom:1px solid #CCC;">{item}</th>';
        $this->_template_rows .= "\n\t\t".'<td style="border-bottom:1px solid #CCC;">{result}</td>';
        $this->_template_rows .= "\n\t".'</tr>';
    }

    // --------------------------------------------------------------------

    /**
     * Parse Template
     *
     * Harvests the data within the template {pseudo-variables}
     *
     * @access	private
     * @return	void
     */
    public function _parse_template()
    {
        if (! is_null($this->_template_rows)) {
            return;
        }

        if (is_null($this->_template)) {
            $this->_default_template();
            return;
        }

        if (! preg_match("/\{rows\}(.*?)\{\/rows\}/si", (string) $this->_template, $match)) {
            $this->_default_template();
            return;
        }

        $this->_template_rows = $match['1'];
        $this->_template = str_replace($match['0'], '{rows}', $this->_template);
    }
}
// END Unit_test Class

/**
 * Helper functions to test boolean true/false
 *
 *
 * @access	private
 * @return	bool
 */
function is_true($test)
{
    return (is_bool($test) and $test === true) ? true : false;
}
function is_false($test)
{
    return (is_bool($test) and $test === false) ? true : false;
}


/* End of file Unit_test.php */
/* Location: ./system/libraries/Unit_test.php */
