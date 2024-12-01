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
 * Database Cache Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_Cache
{
    public $CI;
    public $db;	// allows passing of db object so that multiple database connections and returned db objects can be supported

    /**
     * Constructor
     *
     * Grabs the CI super object instance so we can access it.
     *
     */
    public function __construct(&$db)
    {
        // Assign the main CI object to $this->CI
        // and load the file helper since we use it a lot
        $this->CI =& get_instance();
        $this->db =& $db;
        $this->CI->load->helper('file');
    }

    // --------------------------------------------------------------------

    /**
     * Set Cache Directory Path
     *
     * @access	public
     * @param	string	the path to the cache directory
     * @return	bool
     */
    public function check_path($path = '')
    {
        if ($path == '') {
            if ($this->db->cachedir == '') {
                return $this->db->cache_off();
            }

            $path = $this->db->cachedir;
        }

        // Add a trailing slash to the path if needed
        $path = preg_replace("/(.+?)\/*$/", "\\1/", (string) $path);

        if (! is_dir($path) or ! is_really_writable($path)) {
            // If the path is wrong we'll turn off caching
            return $this->db->cache_off();
        }

        $this->db->cachedir = $path;
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Retrieve a cached query
     *
     * The URI being requested will become the name of the cache sub-folder.
     * An MD5 hash of the SQL statement will become the cache file name
     *
     * @access	public
     * @return	string
     */
    public function read($sql)
    {
        if (! $this->check_path()) {
            return $this->db->cache_off();
        }

        $segment_one = ($this->CI->uri->segment(1) == false) ? 'default' : $this->CI->uri->segment(1);

        $segment_two = ($this->CI->uri->segment(2) == false) ? 'index' : $this->CI->uri->segment(2);

        $filepath = $this->db->cachedir.$segment_one.'+'.$segment_two.'/'.md5((string) $sql);

        if (false === ($cachedata = read_file($filepath))) {
            return false;
        }

        return unserialize($cachedata);
    }

    // --------------------------------------------------------------------

    /**
     * Write a query to a cache file
     *
     * @access	public
     * @return	bool
     */
    public function write($sql, $object)
    {
        if (! $this->check_path()) {
            return $this->db->cache_off();
        }

        $segment_one = ($this->CI->uri->segment(1) == false) ? 'default' : $this->CI->uri->segment(1);

        $segment_two = ($this->CI->uri->segment(2) == false) ? 'index' : $this->CI->uri->segment(2);

        $dir_path = $this->db->cachedir.$segment_one.'+'.$segment_two.'/';

        $filename = md5((string) $sql);

        if (! @is_dir($dir_path)) {
            if (! @mkdir($dir_path, DIR_WRITE_MODE)) {
                return false;
            }

            @chmod($dir_path, DIR_WRITE_MODE);
        }

        if (write_file($dir_path.$filename, serialize($object)) === false) {
            return false;
        }

        @chmod($dir_path.$filename, FILE_WRITE_MODE);
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Delete cache files within a particular directory
     *
     * @access	public
     * @return	bool
     */
    public function delete($segment_one = '', $segment_two = '')
    {
        if ($segment_one == '') {
            $segment_one  = ($this->CI->uri->segment(1) == false) ? 'default' : $this->CI->uri->segment(1);
        }

        if ($segment_two == '') {
            $segment_two = ($this->CI->uri->segment(2) == false) ? 'index' : $this->CI->uri->segment(2);
        }

        $dir_path = $this->db->cachedir.$segment_one.'+'.$segment_two.'/';

        delete_files($dir_path, true);
    }

    // --------------------------------------------------------------------

    /**
     * Delete all existing cache files
     *
     * @access	public
     * @return	bool
     */
    public function delete_all()
    {
        delete_files($this->db->cachedir, true);
    }
}


/* End of file DB_cache.php */
/* Location: ./system/database/DB_cache.php */
