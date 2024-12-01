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
 * Logging Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Logging
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/general/errors.html
 */
class CI_Log
{
    protected $_log_path;
    protected $_threshold	= 1;
    protected $_date_fmt	= 'Y-m-d H:i:s';
    protected $_enabled	= true;
    protected $_levels	= ['ERROR' => '1', 'DEBUG' => '2', 'INFO' => '3', 'ALL' => '4'];

    /**
     * Constructor
     */
    public function __construct()
    {
        $config =& get_config();

        $this->_log_path = ($config['log_path'] != '') ? $config['log_path'] : APPPATH.'logs/';

        if (! is_dir($this->_log_path) or ! is_really_writable($this->_log_path)) {
            $this->_enabled = false;
        }

        if (is_numeric($config['log_threshold'])) {
            $this->_threshold = $config['log_threshold'];
        }

        if ($config['log_date_format'] != '') {
            $this->_date_fmt = $config['log_date_format'];
        }
    }

    // --------------------------------------------------------------------

    /**
     * Write Log File
     *
     * Generally this function will be called using the global log_message() function
     *
     * @param	string	the error level
     * @param	string	the error message
     * @param	bool	whether the error is a native PHP error
     * @return	bool
     */
    public function write_log($level, $msg, $php_error = false)
    {
        if ($this->_enabled === false) {
            return false;
        }

        $level = strtoupper((string) $level);

        if (! isset($this->_levels[$level]) or ($this->_levels[$level] > $this->_threshold)) {
            return false;
        }

        $filepath = $this->_log_path.'log-'.date('Y-m-d').'.php';
        $message  = '';

        if (! file_exists($filepath)) {
            $message .= "<"."?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?".">\n\n";
        }

        if (! $fp = @fopen($filepath, FOPEN_WRITE_CREATE)) {
            return false;
        }

        $message .= $level.' '.(($level == 'INFO') ? ' -' : '-').' '.date($this->_date_fmt). ' --> '.$msg."\n";

        flock($fp, LOCK_EX);
        fwrite($fp, $message);
        flock($fp, LOCK_UN);
        fclose($fp);

        @chmod($filepath, FILE_WRITE_MODE);
        return true;
    }
}
// END Log Class

/* End of file Log.php */
/* Location: ./system/libraries/Log.php */
