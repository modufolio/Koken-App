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
 * @since		Version 2.0.2
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * CUBRID Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		Esen Sagynov
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_cubrid_driver extends CI_DB
{
    // Default CUBRID Broker port. Will be used unless user
    // explicitly specifies another one.
    public const DEFAULT_PORT = 33000;

    public $dbdriver = 'cubrid';

    // The character used for escaping - no need in CUBRID
    public $_escape_char = '';

    // clause and character used for LIKE escape sequences - not used in CUBRID
    public $_like_escape_str = '';
    public $_like_escape_chr = '';

    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the count_all() and count_all_results() functions.
     */
    public $_count_string = 'SELECT COUNT(*) AS ';
    public $_random_keyword = ' RAND()'; // database specific random keyword

    /**
     * Non-persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    public function db_connect()
    {
        // If no port is defined by the user, use the default value
        if ($this->port == '') {
            $this->port = self::DEFAULT_PORT;
        }

        $conn = cubrid_connect($this->hostname, $this->port, $this->database, $this->username, $this->password);

        if ($conn) {
            // Check if a user wants to run queries in dry, i.e. run the
            // queries but not commit them.
            if (isset($this->auto_commit) && ! $this->auto_commit) {
                cubrid_set_autocommit($conn, CUBRID_AUTOCOMMIT_FALSE);
            } else {
                cubrid_set_autocommit($conn, CUBRID_AUTOCOMMIT_TRUE);
                $this->auto_commit = true;
            }
        }

        return $conn;
    }

    // --------------------------------------------------------------------

    /**
     * Persistent database connection
     * In CUBRID persistent DB connection is supported natively in CUBRID
     * engine which can be configured in the CUBRID Broker configuration
     * file by setting the CCI_PCONNECT parameter to ON. In that case, all
     * connections established between the client application and the
     * server will become persistent. This is calling the same
     * @cubrid_connect function will establish persisten connection
     * considering that the CCI_PCONNECT is ON.
     *
     * @access	private called by the base class
     * @return	resource
     */
    public function db_pconnect()
    {
        return $this->db_connect();
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
        if (cubrid_ping($this->conn_id) === false) {
            $this->conn_id = false;
        }
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
        // In CUBRID there is no need to select a database as the database
        // is chosen at the connection time.
        // So, to determine if the database is "selected", all we have to
        // do is ping the server and return that value.
        return cubrid_ping($this->conn_id);
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
        // In CUBRID, there is no need to set charset or collation.
        // This is why returning true will allow the application continue
        // its normal process.
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Version number query string
     *
     * @access	public
     * @return	string
     */
    public function _version()
    {
        // To obtain the CUBRID Server version, no need to run the SQL query.
        // CUBRID PHP API provides a function to determin this value.
        // This is why we also need to add 'cubrid' value to the list of
        // $driver_version_exceptions array in DB_driver class in
        // version() function.
        return cubrid_get_server_info($this->conn_id);
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
        return @cubrid_query($sql, $this->conn_id);
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
        // No need to prepare
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

        if (cubrid_get_autocommit($this->conn_id)) {
            cubrid_set_autocommit($this->conn_id, CUBRID_AUTOCOMMIT_FALSE);
        }

        return true;
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

        cubrid_commit($this->conn_id);

        if ($this->auto_commit && ! cubrid_get_autocommit($this->conn_id)) {
            cubrid_set_autocommit($this->conn_id, CUBRID_AUTOCOMMIT_TRUE);
        }

        return true;
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

        cubrid_rollback($this->conn_id);

        if ($this->auto_commit && ! cubrid_get_autocommit($this->conn_id)) {
            cubrid_set_autocommit($this->conn_id, CUBRID_AUTOCOMMIT_TRUE);
        }

        return true;
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
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escape_str($val, $like);
            }

            return $str;
        }

        if (function_exists('cubrid_real_escape_string') and is_resource($this->conn_id)) {
            $str = cubrid_real_escape_string($str, $this->conn_id);
        } else {
            $str = addslashes($str);
        }

        // escape LIKE condition wildcards
        if ($like === true) {
            $str = str_replace(array('%', '_'), array('\\%', '\\_'), $str);
        }

        return $str;
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
        return @cubrid_affected_rows($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Insert ID
     *
     * @access	public
     * @return	integer
     */
    public function insert_id()
    {
        return @cubrid_insert_id($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified table
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
        $sql = "SHOW TABLES";

        if ($prefix_limit !== false and $this->dbprefix != '') {
            $sql .= " LIKE '".$this->escape_like_str($this->dbprefix)."%'";
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Show column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    public function _list_columns($table = '')
    {
        return "SHOW COLUMNS FROM ".$this->_protect_identifiers($table, true, null, false);
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
        return "SELECT * FROM ".$table." LIMIT 1";
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
        return cubrid_error($this->conn_id);
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
        return cubrid_errno($this->conn_id);
    }

    // --------------------------------------------------------------------

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
        if ($this->_escape_char == '') {
            return $item;
        }

        foreach ($this->_reserved_identifiers as $id) {
            if (strpos($item, '.'.$id) !== false) {
                $str = $this->_escape_char. str_replace('.', $this->_escape_char.'.', $item);

                // remove duplicates if the user already included the escape
                return preg_replace('/['.$this->_escape_char.']+/', $this->_escape_char, $str);
            }
        }

        if (strpos($item, '.') !== false) {
            $str = $this->_escape_char.str_replace('.', $this->_escape_char.'.'.$this->_escape_char, $item).$this->_escape_char;
        } else {
            $str = $this->_escape_char.$item.$this->_escape_char;
        }

        // remove duplicates if the user already included the escape
        return preg_replace('/['.$this->_escape_char.']+/', $this->_escape_char, $str);
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

        return '('.implode(', ', $tables).')';
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
        return "INSERT INTO ".$table." (\"".implode('", "', $keys)."\") VALUES (".implode(', ', $values).")";
    }

    // --------------------------------------------------------------------


    /**
     * Replace statement
     *
     * Generates a platform-specific replace string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    public function _replace($table, $keys, $values)
    {
        return "REPLACE INTO ".$table." (\"".implode('", "', $keys)."\") VALUES (".implode(', ', $values).")";
    }

    // --------------------------------------------------------------------

    /**
     * Insert_batch statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    public function _insert_batch($table, $keys, $values)
    {
        return "INSERT INTO ".$table." (\"".implode('", "', $keys)."\") VALUES ".implode(', ', $values);
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
    public function _update($table, $values, $where, $orderby = array(), $limit = false)
    {
        foreach ($values as $key => $val) {
            $valstr[] = sprintf('"%s" = %s', $key, $val);
        }

        $limit = (! $limit) ? '' : ' LIMIT '.$limit;

        $orderby = (count($orderby) >= 1) ? ' ORDER BY '.implode(", ", $orderby) : '';

        $sql = "UPDATE ".$table." SET ".implode(', ', $valstr);

        $sql .= ($where != '' and count($where) >=1) ? " WHERE ".implode(" ", $where) : '';

        $sql .= $orderby.$limit;

        return $sql;
    }

    // --------------------------------------------------------------------


    /**
     * Update_Batch statement
     *
     * Generates a platform-specific batch update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @return	string
     */
    public function _update_batch($table, $values, $index, $where = null)
    {
        $ids = [];
        $where = ($where != '' and count($where) >=1) ? implode(" ", $where).' AND ' : '';

        foreach ($values as $key => $val) {
            $ids[] = $val[$index];

            foreach (array_keys($val) as $field) {
                if ($field != $index) {
                    $final[$field][] = 'WHEN '.$index.' = '.$val[$index].' THEN '.$val[$field];
                }
            }
        }

        $sql = "UPDATE ".$table." SET ";
        $cases = '';

        foreach ($final as $k => $v) {
            $cases .= $k.' = CASE '."\n";
            foreach ($v as $row) {
                $cases .= $row."\n";
            }

            $cases .= 'ELSE '.$k.' END, ';
        }

        $sql .= substr($cases, 0, -2);

        $sql .= ' WHERE '.$where.$index.' IN ('.implode(',', $ids).')';

        return $sql;
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
        return "TRUNCATE ".$table;
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
    public function _delete($table, $where = array(), $like = array(), $limit = false)
    {
        $conditions = '';

        if (count($where) > 0 or count($like) > 0) {
            $conditions = "\nWHERE ";
            $conditions .= implode("\n", $this->ar_where);

            if (count($where) > 0 && count($like) > 0) {
                $conditions .= " AND ";
            }
            $conditions .= implode("\n", $like);
        }

        $limit = (! $limit) ? '' : ' LIMIT '.$limit;

        return "DELETE FROM ".$table.$conditions.$limit;
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
        if ($offset == 0) {
            $offset = '';
        } else {
            $offset .= ", ";
        }

        return $sql."LIMIT ".$offset.$limit;
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
        @cubrid_close($conn_id);
    }
}


/* End of file cubrid_driver.php */
/* Location: ./system/database/drivers/cubrid/cubrid_driver.php */
