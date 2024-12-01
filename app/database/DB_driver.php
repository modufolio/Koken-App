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
 * Database Driver Class
 *
 * This is the platform-independent base DB implementation class.
 * This class will not be called directly. Rather, the adapter
 * class for the specific database will extend and instantiate it.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_driver
{
    public $username;
    public $password;
    public $hostname;
    public $database;
    public $dbdriver		= 'mysql';
    public $dbprefix		= '';
    public $char_set		= 'utf8';
    public $dbcollat		= 'utf8_general_ci';
    public $autoinit		= true; // Whether to automatically initialize the DB
    public $swap_pre		= '';
    public $port			= '';
    public $pconnect		= false;
    public $conn_id		= false;
    public $result_id		= false;
    public $db_debug		= false;
    public $benchmark		= 0;
    public $query_count	= 0;
    public $bind_marker	= '?';
    public $save_queries	= true;
    public $queries		= [];
    public $query_times	= [];
    public $data_cache		= [];
    public $trans_enabled	= true;
    public $trans_strict	= true;
    public $_trans_depth	= 0;
    public $_trans_status	= true; // Used with transactions to determine if a rollback should occur
    public $cache_on		= false;
    public $cachedir		= '';
    public $cache_autodel	= false;
    public $CACHE; // The cache class object

    // Private variables
    public $_protect_identifiers	= true;
    public $_reserved_identifiers	= ['*']; // Identifiers that should NOT be escaped

    // These are use with Oracle
    public $stmt_id;
    public $curs_id;
    public $limit_used;



    /**
     * Constructor.  Accepts one parameter containing the database
     * connection settings.
     *
     * @param array
     */
    public function __construct($params)
    {
        if (is_array($params)) {
            foreach ($params as $key => $val) {
                $this->$key = $val;
            }
        }

        log_message('debug', 'Database Driver Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Initialize Database Settings
     *
     * @access	private Called by the constructor
     * @param	mixed
     * @return	void
     */
    public function initialize()
    {
        // If an existing connection resource is available
        // there is no need to connect and select the database
        if (is_resource($this->conn_id) or is_object($this->conn_id)) {
            return true;
        }

        // ----------------------------------------------------------------

        // Connect to the database and set the connection ID
        $this->conn_id = ($this->pconnect == false) ? $this->db_connect() : $this->db_pconnect();

        // No connection resource?  Throw an error
        if (! $this->conn_id) {
            log_message('error', 'Unable to connect to the database');

            if ($this->db_debug) {
                $this->display_error('db_unable_to_connect');
            }
            return false;
        }

        // ----------------------------------------------------------------

        // Select the DB... assuming a database name is specified in the config file
        if ($this->database != '') {
            if (! $this->db_select()) {
                log_message('error', 'Unable to select database: '.$this->database);

                if ($this->db_debug) {
                    $this->display_error('db_unable_to_select', $this->database);
                }
                return false;
            } else {
                // We've selected the DB. Now we set the character set
                if (! $this->db_set_charset($this->char_set, $this->dbcollat)) {
                    return false;
                }

                return true;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Set client character set
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	resource
     */
    public function db_set_charset($charset, $collation)
    {
        if (! $this->_db_set_charset($this->char_set, $this->dbcollat)) {
            log_message('error', 'Unable to set database connection charset: '.$this->char_set);

            if ($this->db_debug) {
                $this->display_error('db_unable_to_set_charset', $this->char_set);
            }

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * The name of the platform in use (mysql, mssql, etc...)
     *
     * @access	public
     * @return	string
     */
    public function platform()
    {
        return $this->dbdriver;
    }

    // --------------------------------------------------------------------

    /**
     * Database Version Number.  Returns a string containing the
     * version of the database being used
     *
     * @access	public
     * @return	string
     */
    public function version()
    {
        if (false === ($sql = $this->_version())) {
            if ($this->db_debug) {
                return $this->display_error('db_unsupported_function');
            }
            return false;
        }

        // Some DBs have functions that return the version, and don't run special
        // SQL queries per se. In these instances, just return the result.
        $driver_version_exceptions = ['oci8', 'sqlite', 'cubrid'];

        if (in_array($this->dbdriver, $driver_version_exceptions)) {
            return $sql;
        } else {
            $query = $this->query($sql);
            return $query->row('ver');
        }
    }

    // --------------------------------------------------------------------

    /**
     * Execute the query
     *
     * Accepts an SQL string as input and returns a result object upon
     * successful execution of a "read" type query.  Returns boolean TRUE
     * upon successful execution of a "write" type query. Returns boolean
     * FALSE upon failure, and if the $db_debug variable is set to TRUE
     * will raise an error.
     *
     * @access	public
     * @param	string	An SQL query string
     * @param	array	An array of binding data
     * @return	mixed
     */
    public function query($sql, $binds = false, $return_object = true)
    {
        if ($sql == '') {
            if ($this->db_debug) {
                log_message('error', 'Invalid query: '.$sql);
                return $this->display_error('db_invalid_query');
            }
            return false;
        }

        // Verify table prefix and replace if necessary
        if (($this->dbprefix != '' and $this->swap_pre != '') and ($this->dbprefix != $this->swap_pre)) {
            $sql = preg_replace("/(\W)".$this->swap_pre."(\S+?)/", "\\1".$this->dbprefix."\\2", (string) $sql);
        }

        // Compile binds if needed
        if ($binds !== false) {
            $sql = $this->compile_binds($sql, $binds);
        }

        // Is query caching enabled?  If the query is a "read type"
        // we will load the caching class and return the previously
        // cached query if it exists
        if ($this->cache_on == true and stristr((string) $sql, 'SELECT')) {
            if ($this->_cache_init()) {
                $this->load_rdriver();
                if (false !== ($cache = $this->CACHE->read($sql))) {
                    return $cache;
                }
            }
        }

        // Save the  query for debugging
        if ($this->save_queries == true) {
            $this->queries[] = $sql;
        }

        // Start the Query Timer
        $time_start = [$sm, $ss] = explode(' ', microtime());

        // Run the Query
        if (false === ($this->result_id = $this->simple_query($sql))) {
            if ($this->save_queries == true) {
                $this->query_times[] = 0;
            }

            // This will trigger a rollback if transactions are being used
            $this->_trans_status = false;

            if ($this->db_debug) {
                // grab the error number and message now, as we might run some
                // additional queries before displaying the error
                $error_no = $this->_error_number();
                $error_msg = $this->_error_message();

                // We call this function in order to roll-back queries
                // if transactions are enabled.  If we don't call this here
                // the error message will trigger an exit, causing the
                // transactions to remain in limbo.
                $this->trans_complete();

                // Log and display errors
                log_message('error', 'Query error: '.$error_msg);
                return $this->display_error(
                    ['Error Number: '.$error_no, $error_msg, $sql]
                );
            }

            return false;
        }

        // Stop and aggregate the query time results
        $time_end = [$em, $es] = explode(' ', microtime());
        $this->benchmark += ($em + $es) - ($sm + $ss);

        if ($this->save_queries == true) {
            $this->query_times[] = ($em + $es) - ($sm + $ss);
        }

        // Increment the query counter
        $this->query_count++;

        // Was the query a "write" type?
        // If so we'll simply return true
        if ($this->is_write_type($sql) === true) {
            // If caching is enabled we'll auto-cleanup any
            // existing files related to this particular URI
            if ($this->cache_on == true and $this->cache_autodel == true and $this->_cache_init()) {
                $this->CACHE->delete();
            }

            return true;
        }

        // Return TRUE if we don't need to create a result object
        // Currently only the Oracle driver uses this when stored
        // procedures are used
        if ($return_object !== true) {
            return true;
        }

        // Load and instantiate the result driver

        $driver			= $this->load_rdriver();
        $RES			= new $driver();
        $RES->conn_id	= $this->conn_id;
        $RES->result_id	= $this->result_id;

        if ($this->dbdriver == 'oci8') {
            $RES->stmt_id		= $this->stmt_id;
            $RES->curs_id		= null;
            $RES->limit_used	= $this->limit_used;
            $this->stmt_id		= false;
        }

        // oci8 vars must be set before calling this
        $RES->num_rows	= $RES->num_rows();

        // Is query caching enabled?  If so, we'll serialize the
        // result object and save it to a cache file.
        if ($this->cache_on == true and $this->_cache_init()) {
            // We'll create a new instance of the result object
            // only without the platform specific driver since
            // we can't use it with cached data (the query result
            // resource ID won't be any good once we've cached the
            // result object, so we'll have to compile the data
            // and save it)
            $CR = new CI_DB_result();
            $CR->num_rows		= $RES->num_rows();
            $CR->result_object	= $RES->result_object();
            $CR->result_array	= $RES->result_array();

            // Reset these since cached objects can not utilize resource IDs.
            $CR->conn_id		= null;
            $CR->result_id		= null;

            $this->CACHE->write($sql, $CR);
        }

        return $RES;
    }

    // --------------------------------------------------------------------

    /**
     * Load the result drivers
     *
     * @access	public
     * @return	string	the name of the result class
     */
    public function load_rdriver()
    {
        $driver = 'CI_DB_'.$this->dbdriver.'_result';

        if (! class_exists($driver)) {
            include_once(BASEPATH.'database/DB_result.php');
            include_once(BASEPATH.'database/drivers/'.$this->dbdriver.'/'.$this->dbdriver.'_result.php');
        }

        return $driver;
    }

    // --------------------------------------------------------------------

    /**
     * Simple Query
     * This is a simplified version of the query() function.  Internally
     * we only use it when running transaction commands since they do
     * not require all the features of the main query() function.
     *
     * @access	public
     * @param	string	the sql query
     * @return	mixed
     */
    public function simple_query($sql)
    {
        if (! $this->conn_id) {
            $this->initialize();
        }

        return $this->_execute($sql);
    }

    // --------------------------------------------------------------------

    /**
     * Disable Transactions
     * This permits transactions to be disabled at run-time.
     *
     * @access	public
     * @return	void
     */
    public function trans_off()
    {
        $this->trans_enabled = false;
    }

    // --------------------------------------------------------------------

    /**
     * Enable/disable Transaction Strict Mode
     * When strict mode is enabled, if you are running multiple groups of
     * transactions, if one group fails all groups will be rolled back.
     * If strict mode is disabled, each group is treated autonomously, meaning
     * a failure of one group will not affect any others
     *
     * @access	public
     * @return	void
     */
    public function trans_strict($mode = true)
    {
        $this->trans_strict = is_bool($mode) ? $mode : true;
    }

    // --------------------------------------------------------------------

    /**
     * Start Transaction
     *
     * @access	public
     * @return	void
     */
    public function trans_start($test_mode = false)
    {
        if (! $this->trans_enabled) {
            return false;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0) {
            $this->_trans_depth += 1;
            return;
        }

        $this->trans_begin($test_mode);
    }

    // --------------------------------------------------------------------

    /**
     * Complete Transaction
     *
     * @access	public
     * @return	bool
     */
    public function trans_complete()
    {
        if (! $this->trans_enabled) {
            return false;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 1) {
            $this->_trans_depth -= 1;
            return true;
        }

        // The query() function will set this flag to FALSE in the event that a query failed
        if ($this->_trans_status === false) {
            $this->trans_rollback();

            // If we are NOT running in strict mode, we will reset
            // the _trans_status flag so that subsequent groups of transactions
            // will be permitted.
            if ($this->trans_strict === false) {
                $this->_trans_status = true;
            }

            log_message('debug', 'DB Transaction Failure');
            return false;
        }

        $this->trans_commit();
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Lets you retrieve the transaction flag to determine if it has failed
     *
     * @access	public
     * @return	bool
     */
    public function trans_status()
    {
        return $this->_trans_status;
    }

    // --------------------------------------------------------------------

    /**
     * Compile Bindings
     *
     * @access	public
     * @param	string	the sql statement
     * @param	array	an array of bind data
     * @return	string
     */
    public function compile_binds($sql, $binds)
    {
        if (!str_contains((string) $sql, (string) $this->bind_marker)) {
            return $sql;
        }

        if (! is_array($binds)) {
            $binds = [$binds];
        }

        // Get the sql segments around the bind markers
        $segments = explode($this->bind_marker, (string) $sql);

        // The count of bind should be 1 less then the count of segments
        // If there are more bind arguments trim it down
        if (count($binds) >= count($segments)) {
            $binds = array_slice($binds, 0, count($segments)-1);
        }

        // Construct the binded query
        $result = $segments[0];
        $i = 0;
        foreach ($binds as $bind) {
            $result .= $this->escape($bind);
            $result .= $segments[++$i];
        }

        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * Determines if a query is a "write" type.
     *
     * @access	public
     * @param	string	An SQL query string
     * @return	boolean
     */
    public function is_write_type($sql)
    {
        if (! preg_match('/^\s*"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD DATA|COPY|ALTER|GRANT|REVOKE|LOCK|UNLOCK)\s+/i', (string) $sql)) {
            return false;
        }
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Calculate the aggregate query elapsed time
     *
     * @access	public
     * @param	integer	The number of decimal places
     * @return	integer
     */
    public function elapsed_time($decimals = 6)
    {
        return number_format($this->benchmark, $decimals);
    }

    // --------------------------------------------------------------------

    /**
     * Returns the total number of queries
     *
     * @access	public
     * @return	integer
     */
    public function total_queries()
    {
        return $this->query_count;
    }

    // --------------------------------------------------------------------

    /**
     * Returns the last query that was executed
     *
     * @access	public
     * @return	void
     */
    public function last_query()
    {
        return end($this->queries);
    }

    // --------------------------------------------------------------------

    /**
     * "Smart" Escape String
     *
     * Escapes data based on type
     * Sets boolean and null types
     *
     * @access	public
     * @param	string
     * @return	mixed
     */
    public function escape($str)
    {
        if (is_string($str)) {
            $str = "'".$this->escape_str($str)."'";
        } elseif (is_bool($str)) {
            $str = ($str === false) ? 0 : 1;
        } elseif (is_null($str)) {
            $str = 'NULL';
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Escape LIKE String
     *
     * Calls the individual driver for platform
     * specific escaping for LIKE conditions
     *
     * @access	public
     * @param	string
     * @return	mixed
     */
    public function escape_like_str($str)
    {
        return $this->escape_str($str, true);
    }

    // --------------------------------------------------------------------

    /**
     * Primary
     *
     * Retrieves the primary key.  It assumes that the row in the first
     * position is the primary key
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    public function primary($table = '')
    {
        $fields = $this->list_fields($table);

        if (! is_array($fields)) {
            return false;
        }

        return current($fields);
    }

    // --------------------------------------------------------------------

    /**
     * Returns an array of table names
     *
     * @access	public
     * @return	array
     */
    public function list_tables($constrain_by_prefix = false)
    {
        // Is there a cached result?
        if (isset($this->data_cache['table_names'])) {
            return $this->data_cache['table_names'];
        }

        if (false === ($sql = $this->_list_tables($constrain_by_prefix))) {
            if ($this->db_debug) {
                return $this->display_error('db_unsupported_function');
            }
            return false;
        }

        $retval = [];
        $query = $this->query($sql);

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                if (isset($row['TABLE_NAME'])) {
                    $retval[] = $row['TABLE_NAME'];
                } else {
                    $retval[] = array_shift($row);
                }
            }
        }

        $this->data_cache['table_names'] = $retval;
        return $this->data_cache['table_names'];
    }

    // --------------------------------------------------------------------

    /**
     * Determine if a particular table exists
     * @access	public
     * @return	boolean
     */
    public function table_exists($table_name)
    {
        return (! in_array($this->_protect_identifiers($table_name, true, false, false), $this->list_tables())) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch MySQL Field Names
     *
     * @access	public
     * @param	string	the table name
     * @return	array
     */
    public function list_fields($table = '')
    {
        // Is there a cached result?
        if (isset($this->data_cache['field_names'][$table])) {
            return $this->data_cache['field_names'][$table];
        }

        if ($table == '') {
            if ($this->db_debug) {
                return $this->display_error('db_field_param_missing');
            }
            return false;
        }

        if (false === ($sql = $this->_list_columns($table))) {
            if ($this->db_debug) {
                return $this->display_error('db_unsupported_function');
            }
            return false;
        }

        $query = $this->query($sql);

        $retval = [];
        foreach ($query->result_array() as $row) {
            if (isset($row['COLUMN_NAME'])) {
                $retval[] = $row['COLUMN_NAME'];
            } else {
                $retval[] = current($row);
            }
        }

        $this->data_cache['field_names'][$table] = $retval;
        return $this->data_cache['field_names'][$table];
    }

    // --------------------------------------------------------------------

    /**
     * Determine if a particular field exists
     * @access	public
     * @param	string
     * @param	string
     * @return	boolean
     */
    public function field_exists($field_name, $table_name)
    {
        return (! in_array($field_name, $this->list_fields($table_name))) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Returns an object with field data
     *
     * @access	public
     * @param	string	the table name
     * @return	object
     */
    public function field_data($table = '')
    {
        if ($table == '') {
            if ($this->db_debug) {
                return $this->display_error('db_field_param_missing');
            }
            return false;
        }

        $query = $this->query($this->_field_data($this->_protect_identifiers($table, true, null, false)));

        return $query->field_data();
    }

    // --------------------------------------------------------------------

    /**
     * Generate an insert string
     *
     * @access	public
     * @param	string	the table upon which the query will be performed
     * @param	array	an associative array data of key/values
     * @return	string
     */
    public function insert_string($table, $data)
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $val) {
            $fields[] = $this->_escape_identifiers($key);
            $values[] = $this->escape($val);
        }

        return $this->_insert($this->_protect_identifiers($table, true, null, false), $fields, $values);
    }

    // --------------------------------------------------------------------

    /**
     * Generate an update string
     *
     * @access	public
     * @param	string	the table upon which the query will be performed
     * @param	array	an associative array data of key/values
     * @param	mixed	the "where" statement
     * @return	string
     */
    public function update_string($table, $data, $where)
    {
        if ($where == '') {
            return false;
        }

        $fields = [];
        foreach ($data as $key => $val) {
            $fields[$this->_protect_identifiers($key)] = $this->escape($val);
        }

        if (! is_array($where)) {
            $dest = [$where];
        } else {
            $dest = [];
            foreach ($where as $key => $val) {
                $prefix = (count($dest) == 0) ? '' : ' AND ';

                if ($val !== '') {
                    if (! $this->_has_operator($key)) {
                        $key .= ' =';
                    }

                    $val = ' '.$this->escape($val);
                }

                $dest[] = $prefix.$key.$val;
            }
        }

        return $this->_update($this->_protect_identifiers($table, true, null, false), $fields, $dest);
    }

    // --------------------------------------------------------------------

    /**
     * Tests whether the string has an SQL operator
     *
     * @access	private
     * @param	string
     * @return	bool
     */
    public function _has_operator($str)
    {
        $str = trim((string) $str);
        if (! preg_match("/(\s|<|>|!|=|is null|is not null)/i", $str)) {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Enables a native PHP function to be run, using a platform agnostic wrapper.
     *
     * @access	public
     * @param	string	the function name
     * @param	mixed	any parameters needed by the function
     * @return	mixed
     */
    public function call_function($function)
    {
        $driver = ($this->dbdriver == 'postgre') ? 'pg_' : $this->dbdriver.'_';

        if (!str_contains($driver, (string) $function)) {
            $function = $driver.$function;
        }

        if (! function_exists($function)) {
            if ($this->db_debug) {
                return $this->display_error('db_unsupported_function');
            }
            return false;
        } else {
            $func_args = func_get_args();
            $args = (func_num_args() > 1) ? array_splice($func_args, 1) : null;
            if (is_null($args)) {
                return call_user_func($function);
            } else {
                return call_user_func_array($function, $args);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Set Cache Directory Path
     *
     * @access	public
     * @param	string	the path to the cache directory
     * @return	void
     */
    public function cache_set_path($path = '')
    {
        $this->cachedir = $path;
    }

    // --------------------------------------------------------------------

    /**
     * Enable Query Caching
     *
     * @access	public
     * @return	void
     */
    public function cache_on()
    {
        $this->cache_on = true;
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Disable Query Caching
     *
     * @access	public
     * @return	void
     */
    public function cache_off()
    {
        $this->cache_on = false;
        return false;
    }


    // --------------------------------------------------------------------

    /**
     * Delete the cache files associated with a particular URI
     *
     * @access	public
     * @return	void
     */
    public function cache_delete($segment_one = '', $segment_two = '')
    {
        if (! $this->_cache_init()) {
            return false;
        }
        return $this->CACHE->delete($segment_one, $segment_two);
    }

    // --------------------------------------------------------------------

    /**
     * Delete All cache files
     *
     * @access	public
     * @return	void
     */
    public function cache_delete_all()
    {
        if (! $this->_cache_init()) {
            return false;
        }

        return $this->CACHE->delete_all();
    }

    // --------------------------------------------------------------------

    /**
     * Initialize the Cache Class
     *
     * @access	private
     * @return	void
     */
    public function _cache_init()
    {
        if (is_object($this->CACHE) and class_exists('CI_DB_Cache')) {
            return true;
        }

        if (! class_exists('CI_DB_Cache')) {
            if (! @include(BASEPATH.'database/DB_cache.php')) {
                return $this->cache_off();
            }
        }

        $this->CACHE = new CI_DB_Cache($this); // pass db object to support multiple db connections and returned db objects
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Close DB Connection
     *
     * @access	public
     * @return	void
     */
    public function close()
    {
        if (is_resource($this->conn_id) or is_object($this->conn_id)) {
            $this->_close($this->conn_id);
        }
        $this->conn_id = false;
    }

    // --------------------------------------------------------------------

    /**
     * Display an error message
     *
     * @access	public
     * @param	string	the error message
     * @param	string	any "swap" values
     * @param	boolean	whether to localize the message
     * @return	string	sends the application/error_db.php template
     */
    public function display_error($error = '', $swap = '', $native = false)
    {
        $LANG =& load_class('Lang', 'core');
        $LANG->load('db');

        $heading = $LANG->line('db_error_heading');

        if ($native == true) {
            $message = $error;
        } else {
            $message = (! is_array($error)) ? [str_replace('%s', $swap, $LANG->line($error))] : $error;
        }

        // Find the most likely culprit of the error by going through
        // the backtrace until the source file is no longer in the
        // database folder.

        $trace = debug_backtrace();

        foreach ($trace as $call) {
            if (isset($call['file']) && !str_contains($call['file'], BASEPATH.'database')) {
                // Found it - use a relative path for safety
                $message[] = 'Filename: '.str_replace([BASEPATH, APPPATH], '', $call['file']);
                $message[] = 'Line Number: '.$call['line'];

                break;
            }
        }

        $error =& load_class('Exceptions', 'core');
        echo $error->show_error($heading, $message, 'error_db');
        exit;
    }

    // --------------------------------------------------------------------

    /**
     * Protect Identifiers
     *
     * This function adds backticks if appropriate based on db type
     *
     * @access	private
     * @param	mixed	the item to escape
     * @return	mixed	the item with backticks
     */
    public function protect_identifiers($item, $prefix_single = false)
    {
        return $this->_protect_identifiers($item, $prefix_single);
    }

    // --------------------------------------------------------------------

    /**
     * Protect Identifiers
     *
     * This function is used extensively by the Active Record class, and by
     * a couple functions in this class.
     * It takes a column or table name (optionally with an alias) and inserts
     * the table prefix onto it.  Some logic is necessary in order to deal with
     * column names that include the path.  Consider a query like this:
     *
     * SELECT * FROM hostname.database.table.column AS c FROM hostname.database.table
     *
     * Or a query with aliasing:
     *
     * SELECT m.member_id, m.member_name FROM members AS m
     *
     * Since the column name can include up to four segments (host, DB, table, column)
     * or also have an alias prefix, we need to do a bit of work to figure this out and
     * insert the table prefix (if it exists) in the proper position, and escape only
     * the correct identifiers.
     *
     * @access	private
     * @param	string
     * @param	bool
     * @param	mixed
     * @param	bool
     * @return	string
     */
    public function _protect_identifiers($item, $prefix_single = false, $protect_identifiers = null, $field_exists = true)
    {
        if (! is_bool($protect_identifiers)) {
            $protect_identifiers = $this->_protect_identifiers;
        }

        if (is_array($item)) {
            $escaped_array = [];

            foreach ($item as $k => $v) {
                $escaped_array[$this->_protect_identifiers($k)] = $this->_protect_identifiers($v);
            }

            return $escaped_array;
        }

        // HACK to allow fns in datamapper selects
        if (str_contains((string) $item, '(')) {
            return $item; // Note this is different!
        }

        // Convert tabs or multiple spaces into single spaces
        $item = preg_replace('/[\t ]+/', ' ', (string) $item);

        // If the item has an alias declaration we remove it and set it aside.
        // Basically we remove everything to the right of the first space
        if (str_contains((string) $item, ' ')) {
            $alias = strstr((string) $item, ' ');
            $item = substr((string) $item, 0, - strlen($alias));
        } else {
            $alias = '';
        }

        // This is basically a bug fix for queries that use MAX, MIN, etc.
        // If a parenthesis is found we know that we do not need to
        // escape the data or add a prefix.  There's probably a more graceful
        // way to deal with this, but I'm not thinking of it -- Rick
        if (str_contains((string) $item, '(')) {
            return $item.$alias;
        }

        // Break the string apart if it contains periods, then insert the table prefix
        // in the correct location, assuming the period doesn't indicate that we're dealing
        // with an alias. While we're at it, we will escape the components
        if (str_contains((string) $item, '.')) {
            $parts	= explode('.', (string) $item);

            // Does the first segment of the exploded item match
            // one of the aliases previously identified?  If so,
            // we have nothing more to do other than escape the item
            if (in_array($parts[0], $this->ar_aliased_tables)) {
                if ($protect_identifiers === true) {
                    foreach ($parts as $key => $val) {
                        if (! in_array($val, $this->_reserved_identifiers)) {
                            $parts[$key] = $this->_escape_identifiers($val);
                        }
                    }

                    $item = implode('.', $parts);
                }
                return $item.$alias;
            }

            // Is there a table prefix defined in the config file?  If not, no need to do anything
            if ($this->dbprefix != '') {
                // We now add the table prefix based on some logic.
                // Do we have 4 segments (hostname.database.table.column)?
                // If so, we add the table prefix to the column name in the 3rd segment.
                if (isset($parts[3])) {
                    $i = 2;
                }
                // Do we have 3 segments (database.table.column)?
                // If so, we add the table prefix to the column name in 2nd position
                elseif (isset($parts[2])) {
                    $i = 1;
                }
                // Do we have 2 segments (table.column)?
                // If so, we add the table prefix to the column name in 1st segment
                else {
                    $i = 0;
                }

                // This flag is set when the supplied $item does not contain a field name.
                // This can happen when this function is being called from a JOIN.
                if ($field_exists == false) {
                    $i++;
                }

                // Verify table prefix and replace if necessary
                if ($this->swap_pre != '' && str_starts_with($parts[$i], (string) $this->swap_pre)) {
                    $parts[$i] = preg_replace("/^".$this->swap_pre."(\S+?)/", $this->dbprefix."\\1", $parts[$i]);
                }

                // We only add the table prefix if it does not already exist
                if (!str_starts_with((string) $parts[$i], (string) $this->dbprefix)) {
                    $parts[$i] = $this->dbprefix.$parts[$i];
                }

                // Put the parts back together
                $item = implode('.', $parts);
            }

            if ($protect_identifiers === true) {
                $item = $this->_escape_identifiers($item);
            }

            return $item.$alias;
        }

        // Is there a table prefix?  If not, no need to insert it
        if ($this->dbprefix != '') {
            // Verify table prefix and replace if necessary
            if ($this->swap_pre != '' && str_starts_with((string) $item, (string) $this->swap_pre)) {
                $item = preg_replace("/^".$this->swap_pre."(\S+?)/", $this->dbprefix."\\1", (string) $item);
            }

            // Do we prefix an item with no segments?
            if ($prefix_single == true and !str_starts_with((string) $item, (string) $this->dbprefix)) {
                $item = $this->dbprefix.$item;
            }
        }

        if ($protect_identifiers === true and ! in_array($item, $this->_reserved_identifiers)) {
            $item = $this->_escape_identifiers($item);
        }

        return $item.$alias;
    }

    // --------------------------------------------------------------------

    /**
     * Dummy method that allows Active Record class to be disabled
     *
     * This function is used extensively by every db driver.
     *
     * @return	void
     */
    protected function _reset_select()
    {
    }
}

/* End of file DB_driver.php */
/* Location: ./system/database/DB_driver.php */
