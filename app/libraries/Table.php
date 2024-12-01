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
 * HTML Table Generating Class
 *
 * Lets you create tables manually or from database result objects, or arrays.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	HTML Tables
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/uri.html
 */
class CI_Table
{
    public $rows				= [];
    public $heading			= [];
    public $auto_heading		= true;
    public $caption			= null;
    public $template			= null;
    public $newline			= "\n";
    public $empty_cells		= "";
    public $function			= false;

    public function __construct()
    {
        log_message('debug', "Table Class Initialized");
    }

    // --------------------------------------------------------------------

    /**
     * Set the template
     *
     * @access	public
     * @param	array
     * @return	void
     */
    public function set_template($template)
    {
        if (! is_array($template)) {
            return false;
        }

        $this->template = $template;
    }

    // --------------------------------------------------------------------

    /**
     * Set the table heading
     *
     * Can be passed as an array or discreet params
     *
     * @access	public
     * @param	mixed
     * @return	void
     */
    public function set_heading()
    {
        $args = func_get_args();
        $this->heading = $this->_prep_args($args);
    }

    // --------------------------------------------------------------------

    /**
     * Set columns.  Takes a one-dimensional array as input and creates
     * a multi-dimensional array with a depth equal to the number of
     * columns.  This allows a single array with many elements to  be
     * displayed in a table that has a fixed column count.
     *
     * @access	public
     * @param	array
     * @param	int
     * @return	void
     */
    public function make_columns($array = [], $col_limit = 0)
    {
        if (! is_array($array) or count($array) == 0) {
            return false;
        }

        // Turn off the auto-heading feature since it's doubtful we
        // will want headings from a one-dimensional array
        $this->auto_heading = false;

        if ($col_limit == 0) {
            return $array;
        }

        $new = [];
        while (count($array) > 0) {
            $temp = array_splice($array, 0, $col_limit);

            if (count($temp) < $col_limit) {
                for ($i = count($temp); $i < $col_limit; $i++) {
                    $temp[] = '&nbsp;';
                }
            }

            $new[] = $temp;
        }

        return $new;
    }

    // --------------------------------------------------------------------

    /**
     * Set "empty" cells
     *
     * Can be passed as an array or discreet params
     *
     * @access	public
     * @param	mixed
     * @return	void
     */
    public function set_empty($value)
    {
        $this->empty_cells = $value;
    }

    // --------------------------------------------------------------------

    /**
     * Add a table row
     *
     * Can be passed as an array or discreet params
     *
     * @access	public
     * @param	mixed
     * @return	void
     */
    public function add_row()
    {
        $args = func_get_args();
        $this->rows[] = $this->_prep_args($args);
    }

    // --------------------------------------------------------------------

    /**
     * Prep Args
     *
     * Ensures a standard associative array format for all cell data
     *
     * @access	public
     * @param	type
     * @return	type
     */
    public function _prep_args($args)
    {
        // If there is no $args[0], skip this and treat as an associative array
        // This can happen if there is only a single key, for example this is passed to table->generate
        // array(array('foo'=>'bar'))
        if (isset($args[0]) and (count($args) == 1 && is_array($args[0]))) {
            // args sent as indexed array
            if (! isset($args[0]['data'])) {
                foreach ($args[0] as $key => $val) {
                    if (is_array($val) && isset($val['data'])) {
                        $args[$key] = $val;
                    } else {
                        $args[$key] = ['data' => $val];
                    }
                }
            }
        } else {
            foreach ($args as $key => $val) {
                if (! is_array($val)) {
                    $args[$key] = ['data' => $val];
                }
            }
        }

        return $args;
    }

    // --------------------------------------------------------------------

    /**
     * Add a table caption
     *
     * @access	public
     * @param	string
     * @return	void
     */
    public function set_caption($caption)
    {
        $this->caption = $caption;
    }

    // --------------------------------------------------------------------

    /**
     * Generate the table
     *
     * @access	public
     * @param	mixed
     * @return	string
     */
    public function generate($table_data = null)
    {
        // The table data can optionally be passed to this function
        // either as a database result object or an array
        if (! is_null($table_data)) {
            if (is_object($table_data)) {
                $this->_set_from_object($table_data);
            } elseif (is_array($table_data)) {
                $set_heading = (count($this->heading) == 0 and $this->auto_heading == false) ? false : true;
                $this->_set_from_array($table_data, $set_heading);
            }
        }

        // Is there anything to display?  No?  Smite them!
        if (count($this->heading) == 0 and count($this->rows) == 0) {
            return 'Undefined table data';
        }

        // Compile and validate the template date
        $this->_compile_template();

        // set a custom cell manipulation function to a locally scoped variable so its callable
        $function = $this->function;

        // Build the table!

        $out = $this->template['table_open'];
        $out .= $this->newline;

        // Add any caption here
        if ($this->caption) {
            $out .= $this->newline;
            $out .= '<caption>' . $this->caption . '</caption>';
            $out .= $this->newline;
        }

        // Is there a table heading to display?
        if (count($this->heading) > 0) {
            $out .= $this->template['thead_open'];
            $out .= $this->newline;
            $out .= $this->template['heading_row_start'];
            $out .= $this->newline;

            foreach ($this->heading as $heading) {
                $temp = $this->template['heading_cell_start'];

                foreach ($heading as $key => $val) {
                    if ($key != 'data') {
                        $temp = str_replace('<th', "<th $key='$val'", $temp);
                    }
                }

                $out .= $temp;
                $out .= $heading['data'] ?? '';
                $out .= $this->template['heading_cell_end'];
            }

            $out .= $this->template['heading_row_end'];
            $out .= $this->newline;
            $out .= $this->template['thead_close'];
            $out .= $this->newline;
        }

        // Build the table rows
        if (count($this->rows) > 0) {
            $out .= $this->template['tbody_open'];
            $out .= $this->newline;

            $i = 1;
            foreach ($this->rows as $row) {
                if (! is_array($row)) {
                    break;
                }

                // We use modulus to alternate the row colors
                $name = (fmod($i++, 2)) ? '' : 'alt_';

                $out .= $this->template['row_'.$name.'start'];
                $out .= $this->newline;

                foreach ($row as $cell) {
                    $temp = $this->template['cell_'.$name.'start'];

                    foreach ($cell as $key => $val) {
                        if ($key != 'data') {
                            $temp = str_replace('<td', "<td $key='$val'", $temp);
                        }
                    }

                    $cell = $cell['data'] ?? '';
                    $out .= $temp;

                    if ($cell === "" or $cell === null) {
                        $out .= $this->empty_cells;
                    } else {
                        if ($function !== false && is_callable($function)) {
                            $out .= call_user_func($function, $cell);
                        } else {
                            $out .= $cell;
                        }
                    }

                    $out .= $this->template['cell_'.$name.'end'];
                }

                $out .= $this->template['row_'.$name.'end'];
                $out .= $this->newline;
            }

            $out .= $this->template['tbody_close'];
            $out .= $this->newline;
        }

        $out .= $this->template['table_close'];

        // Clear table class properties before generating the table
        $this->clear();

        return $out;
    }

    // --------------------------------------------------------------------

    /**
     * Clears the table arrays.  Useful if multiple tables are being generated
     *
     * @access	public
     * @return	void
     */
    public function clear()
    {
        $this->rows				= [];
        $this->heading			= [];
        $this->auto_heading		= true;
    }

    // --------------------------------------------------------------------

    /**
     * Set table data from a database result object
     *
     * @access	public
     * @param	object
     * @return	void
     */
    public function _set_from_object($query)
    {
        if (! is_object($query)) {
            return false;
        }

        // First generate the headings from the table column names
        if (count($this->heading) == 0) {
            if (! method_exists($query, 'list_fields')) {
                return false;
            }

            $this->heading = $this->_prep_args($query->list_fields());
        }

        // Next blast through the result array and build out the rows

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $this->rows[] = $this->_prep_args($row);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Set table data from an array
     *
     * @access	public
     * @param	array
     * @return	void
     */
    public function _set_from_array($data, $set_heading = true)
    {
        if (! is_array($data) or count($data) == 0) {
            return false;
        }

        $i = 0;
        foreach ($data as $row) {
            // If a heading hasn't already been set we'll use the first row of the array as the heading
            if ($i == 0 and count($data) > 1 and count($this->heading) == 0 and $set_heading == true) {
                $this->heading = $this->_prep_args($row);
            } else {
                $this->rows[] = $this->_prep_args($row);
            }

            $i++;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Compile Template
     *
     * @access	private
     * @return	void
     */
    public function _compile_template()
    {
        if ($this->template == null) {
            $this->template = $this->_default_template();
            return;
        }

        $this->temp = $this->_default_template();
        foreach (['table_open', 'thead_open', 'thead_close', 'heading_row_start', 'heading_row_end', 'heading_cell_start', 'heading_cell_end', 'tbody_open', 'tbody_close', 'row_start', 'row_end', 'cell_start', 'cell_end', 'row_alt_start', 'row_alt_end', 'cell_alt_start', 'cell_alt_end', 'table_close'] as $val) {
            if (! isset($this->template[$val])) {
                $this->template[$val] = $this->temp[$val];
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Default Template
     *
     * @access	private
     * @return	void
     */
    public function _default_template()
    {
        return  ['table_open'			=> '<table border="0" cellpadding="4" cellspacing="0">', 'thead_open'			=> '<thead>', 'thead_close'			=> '</thead>', 'heading_row_start'		=> '<tr>', 'heading_row_end'		=> '</tr>', 'heading_cell_start'	=> '<th>', 'heading_cell_end'		=> '</th>', 'tbody_open'			=> '<tbody>', 'tbody_close'			=> '</tbody>', 'row_start'				=> '<tr>', 'row_end'				=> '</tr>', 'cell_start'			=> '<td>', 'cell_end'				=> '</td>', 'row_alt_start'		=> '<tr>', 'row_alt_end'			=> '</tr>', 'cell_alt_start'		=> '<td>', 'cell_alt_end'			=> '</td>', 'table_close'			=> '</table>'];
    }
}


/* End of file Table.php */
/* Location: ./system/libraries/Table.php */
