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
 * SQLSRV Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_sqlsrv_driver extends CI_DB
{
    public $dbdriver = 'sqlsrv';

    // The character used for escaping
    public $_escape_char = '';

    // clause and character used for LIKE escape sequences
    public $_like_escape_str = " ESCAPE '%s' ";
    public $_like_escape_chr = '!';

    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the count_all() and count_all_results() functions.
     */
    public $_count_string = "SELECT COUNT(*) AS ";
    public $_random_keyword = ' ASC'; // not currently supported

    /**
     * Non-persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    public function db_connect($pooling = false)
    {
        // Check for a UTF-8 charset being passed as CI's default 'utf8'.
        $character_set = (0 === strcasecmp('utf8', $this->char_set)) ? 'UTF-8' : $this->char_set;

        $connection = array(
            'UID'				=> empty($this->username) ? '' : $this->username,
            'PWD'				=> empty($this->password) ? '' : $this->password,
            'Database'			=> $this->database,
            'ConnectionPooling' => $pooling ? 1 : 0,
            'CharacterSet'		=> $character_set,
            'ReturnDatesAsStrings' => 1
        );

        // If the username and password are both empty, assume this is a
        // 'Windows Authentication Mode' connection.
        if (empty($connection['UID']) && empty($connection['PWD'])) {
            unset($connection['UID'], $connection['PWD']);
        }

        return sqlsrv_connect($this->hostname, $connection);
    }

    // --------------------------------------------------------------------

    /**
     * Persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    public function db_pconnect()
    {
        $this->db_connect(true);
    }

    // --------------------------------------------------------------------

    /**
     * Reconnect
     *
     * Keep / reestablish the db connection if no queries have been
     * sent for a length of time exceeding the server's idle timeout
     *
     * @access	public
     * @return	void
     */
    public function reconnect()
    {
        // not implemented in MSSQL
    }

    // --------------------------------------------------------------------

    /**
     * Select the database
     *
     * @access	private called by the base class
     * @return	resource
     */
    public function db_select()
    {
        return $this->_execute('USE ' . $this->database);
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
        // @todo - add support if needed
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Execute the query
     *
     * @access	private called by the base class
     * @param	string	an SQL query
     * @return	resource
     */
    public function _execute($sql)
    {
        $sql = $this->_prep_query($sql);
        return sqlsrv_query($this->conn_id, $sql, null, array(
            'Scrollable'				=> SQLSRV_CURSOR_STATIC,
            'SendStreamParamsAtExec'	=> true
        ));
    }

    // --------------------------------------------------------------------

    /**
     * Prep the query
     *
     * If needed, each database adapter can prep the query string
     *
     * @access	private called by execute()
     * @param	string	an SQL query
     * @return	string
     */
    public function _prep_query($sql)
    {
        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Begin Transaction
     *
     * @access	public
     * @return	bool
     */
    public function trans_begin($test_mode = false)
    {
        if (! $this->trans_enabled) {
            return true;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0) {
            return true;
        }

        // Reset the transaction failure flag.
        // If the $test_mode flag is set to TRUE transactions will be rolled back
        // even if the queries produce a successful result.
        $this->_trans_failure = ($test_mode === true) ? true : false;

        return sqlsrv_begin_transaction($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Commit Transaction
     *
     * @access	public
     * @return	bool
     */
    public function trans_commit()
    {
        if (! $this->trans_enabled) {
            return true;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0) {
            return true;
        }

        return sqlsrv_commit($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Rollback Transaction
     *
     * @access	public
     * @return	bool
     */
    public function trans_rollback()
    {
        if (! $this->trans_enabled) {
            return true;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0) {
            return true;
        }

        return sqlsrv_rollback($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Escape String
     *
     * @access	public
     * @param	string
     * @param	bool	whether or not the string will be used in a LIKE condition
     * @return	string
     */
    public function escape_str($str, $like = false)
    {
        // Escape single quotes
        return str_replace("'", "''", $str);
    }

    // --------------------------------------------------------------------

    /**
     * Affected Rows
     *
     * @access	public
     * @return	integer
     */
    public function affected_rows()
    {
        return @sqlrv_rows_affected($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
    * Insert ID
    *
    * Returns the last id created in the Identity column.
    *
    * @access public
    * @return integer
    */
    public function insert_id()
    {
        return $this->query('select @@IDENTITY as insert_id')->row('insert_id');
    }

    // --------------------------------------------------------------------

    /**
    * Parse major version
    *
    * Grabs the major version number from the
    * database server version string passed in.
    *
    * @access private
    * @param string $version
    * @return int16 major version number
    */
    public function _parse_major_version($version)
    {
        preg_match('/([0-9]+)\.([0-9]+)\.([0-9]+)/', $version, $ver_info);
        return $ver_info[1]; // return the major version b/c that's all we're interested in.
    }

    // --------------------------------------------------------------------

    /**
    * Version number query string
    *
    * @access public
    * @return string
    */
    public function _version()
    {
        $info = sqlsrv_server_info($this->conn_id);
        return sprintf("select '%s' as ver", $info['SQLServerVersion']);
    }

    // --------------------------------------------------------------------

    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified database
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function count_all($table = '')
    {
        if ($table == '') {
            return 0;
        }

        $query = $this->query($this->_count_string . $this->_protect_identifiers('numrows') . " FROM " . $this->_protect_identifiers($table, true, null, false));

        if ($query->num_rows() == 0) {
            return 0;
        }

        $row = $query->row();
        $this->_reset_select();
        return (int) $row->numrows;
    }

    // --------------------------------------------------------------------

    /**
     * List table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access	private
     * @param	boolean
     * @return	string
     */
    public function _list_tables($prefix_limit = false)
    {
        return "SELECT name FROM sysobjects WHERE type = 'U' ORDER BY name";
    }

    // --------------------------------------------------------------------

    /**
     * List column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access	private
     * @param	string	the table name
     * @return	string
     */
    public function _list_columns($table = '')
    {
        return "SELECT * FROM INFORMATION_SCHEMA.Columns WHERE TABLE_NAME = '".$this->_escape_table($table)."'";
    }

    // --------------------------------------------------------------------

    /**
     * Field data query
     *
     * Generates a platform-specific query so that the column data can be retrieved
     *
     * @access	public
     * @param	string	the table name
     * @return	object
     */
    public function _field_data($table)
    {
        return "SELECT TOP 1 * FROM " . $this->_escape_table($table);
    }

    // --------------------------------------------------------------------

    /**
     * The error message string
     *
     * @access	private
     * @return	string
     */
    public function _error_message()
    {
        $error = array_shift(sqlsrv_errors());
        return !empty($error['message']) ? $error['message'] : null;
    }

    // --------------------------------------------------------------------

    /**
     * The error message number
     *
     * @access	private
     * @return	integer
     */
    public function _error_number()
    {
        $error = array_shift(sqlsrv_errors());
        return isset($error['SQLSTATE']) ? $error['SQLSTATE'] : null;
    }

    // --------------------------------------------------------------------

    /**
     * Escape Table Name
     *
     * This function adds backticks if the table name has a period
     * in it. Some DBs will get cranky unless periods are escaped
     *
     * @access	private
     * @param	string	the table name
     * @return	string
     */
    public function _escape_table($table)
    {
        return $table;
    }


    /**
     * Escape the SQL Identifiers
     *
     * This function escapes column and table names
     *
     * @access	private
     * @param	string
     * @return	string
     */
    public function _escape_identifiers($item)
    {
        return $item;
    }

    // --------------------------------------------------------------------

    /**
     * From Tables
     *
     * This function implicitly groups FROM tables so there is no confusion
     * about operator precedence in harmony with SQL standards
     *
     * @access	public
     * @param	type
     * @return	type
     */
    public function _from_tables($tables)
    {
        if (! is_array($tables)) {
            $tables = array($tables);
        }

        return implode(', ', $tables);
    }

    // --------------------------------------------------------------------

    /**
     * Insert statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    public function _insert($table, $keys, $values)
    {
        return "INSERT INTO ".$this->_escape_table($table)." (".implode(', ', $keys).") VALUES (".implode(', ', $values).")";
    }

    // --------------------------------------------------------------------

    /**
     * Update statement
     *
     * Generates a platform-specific update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @param	array	the orderby clause
     * @param	array	the limit clause
     * @return	string
     */
    public function _update($table, $values, $where)
    {
        foreach ($values as $key => $val) {
            $valstr[] = $key." = ".$val;
        }

        return "UPDATE ".$this->_escape_table($table)." SET ".implode(', ', $valstr)." WHERE ".implode(" ", $where);
    }

    // --------------------------------------------------------------------

    /**
     * Truncate statement
     *
     * Generates a platform-specific truncate string from the supplied data
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    public function _truncate($table)
    {
        return "TRUNCATE TABLE ".$table;
    }

    // --------------------------------------------------------------------

    /**
     * Delete statement
     *
     * Generates a platform-specific delete string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the where clause
     * @param	string	the limit clause
     * @return	string
     */
    public function _delete($table, $where)
    {
        return "DELETE FROM ".$this->_escape_table($table)." WHERE ".implode(" ", $where);
    }

    // --------------------------------------------------------------------

    /**
     * Limit string
     *
     * Generates a platform-specific LIMIT clause
     *
     * @access	public
     * @param	string	the sql query string
     * @param	integer	the number of rows to limit the query to
     * @param	integer	the offset value
     * @return	string
     */
    public function _limit($sql, $limit, $offset)
    {
        $i = $limit + $offset;

        return preg_replace('/(^\SELECT (DISTINCT)?)/i', '\\1 TOP '.$i.' ', $sql);
    }

    // --------------------------------------------------------------------

    /**
     * Close DB Connection
     *
     * @access	public
     * @param	resource
     * @return	void
     */
    public function _close($conn_id)
    {
        @sqlsrv_close($conn_id);
    }
}



/* End of file sqlsrv_driver.php */
/* Location: ./system/database/drivers/sqlsrv/sqlsrv_driver.php */
