<?php

 if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Data Mapper ORM bootstrap
 *
 * Replacement DB class
 *
 * @license 	MIT License
 * @package		DataMapper ORM
 * @category	DataMapper ORM
 * @author  	Harro "WanWizard" Verton
 * @link		http://datamapper.wanwizard.eu/
 * @version 	2.0.0
 */

// ------------------------------------------------------------------------

/**
 * Initialize the database
 *
 * @category	Database
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
function &DB($params = '', $active_record_override = null)
{
    // Load the DB config file if a DSN string wasn't passed
    if (is_string($params) and !str_contains($params, '://')) {
        // Is the config file in the environment folder?
        if (! defined('ENVIRONMENT') or ! file_exists($file_path = APPPATH.'config/'.ENVIRONMENT.'/database.php')) {
            if (! file_exists($file_path = APPPATH.'config/database.php')) {
                show_error('The configuration file database.php does not exist.');
            }
        }

        include($file_path);

        if (! isset($db) or count($db) == 0) {
            show_error('No database connection settings were found in the database config file.');
        }

        if ($params != '') {
            $active_group = $params;
        }

        if (! isset($active_group) or ! isset($db[$active_group])) {
            show_error('You have specified an invalid database connection group.');
        }

        $params = $db[$active_group];
    } elseif (is_string($params)) {

        /* parse the URL from the DSN string
         *  Database settings can be passed as discreet
         *  parameters or as a data source name in the first
         *  parameter. DSNs must have this prototype:
         *  $dsn = 'driver://username:password@hostname/database';
         */

        if (($dns = @parse_url($params)) === false) {
            show_error('Invalid DB Connection String');
        }

        $params = ['dbdriver'	=> $dns['scheme'], 'hostname'	=> (isset($dns['host'])) ? rawurldecode($dns['host']) : '', 'username'	=> (isset($dns['user'])) ? rawurldecode($dns['user']) : '', 'password'	=> (isset($dns['pass'])) ? rawurldecode($dns['pass']) : '', 'database'	=> (isset($dns['path'])) ? rawurldecode(substr($dns['path'], 1)) : ''];

        // were additional config items set?
        if (isset($dns['query'])) {
            parse_str($dns['query'], $extra);

            foreach ($extra as $key => $val) {
                // booleans please
                if (strtoupper($val) == "TRUE") {
                    $val = true;
                } elseif (strtoupper($val) == "FALSE") {
                    $val = false;
                }

                $params[$key] = $val;
            }
        }
    }

    // No DB specified yet?  Beat them senseless...
    if (! isset($params['dbdriver']) or $params['dbdriver'] == '') {
        show_error('You have not selected a database type to connect to.');
    }

    // Load the DB classes.  Note: Since the active record class is optional
    // we need to dynamically create a class that extends proper parent class
    // based on whether we're using the active record class or not.
    // Kudos to Paul for discovering this clever use of eval()

    if ($active_record_override !== null) {
        $active_record = $active_record_override;
    }

    require_once(BASEPATH.'database/DB_driver.php');

    if (! isset($active_record) or $active_record == true) {
        require_once(BASEPATH.'database/DB_active_rec.php');

        if (! class_exists('CI_DB')) {
            eval('class CI_DB extends CI_DB_active_record { }');
        }
    } else {
        if (! class_exists('CI_DB')) {
            eval('class CI_DB extends CI_DB_driver { }');
        }
    }

    require_once(BASEPATH.'database/drivers/'.$params['dbdriver'].'/'.$params['dbdriver'].'_driver.php');

    // Instantiate the DB adapter
    $driver = 'CI_DB_'.$params['dbdriver'].'_driver';

    // load Datamappers DB interceptor class
    require(APPPATH.'third_party/datamapper/system/DB_driver.php');

    $DB = new $driver($params);

    if ($DB->autoinit == true) {
        $DB->initialize();
    }

    if (isset($params['stricton']) && $params['stricton'] == true) {
        $DB->query('SET SESSION sql_mode=""');
    }

    return $DB;
}

/* End of file DB.php */
/* Location: ./application/third_party/datamapper/system/DB.php */
