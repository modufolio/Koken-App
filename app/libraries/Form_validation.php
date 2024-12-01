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
 * Form Validation Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Validation
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/form_validation.html
 */
class CI_Form_validation
{
    protected $CI;
    protected $_field_data			= [];
    protected $_error_array			= [];
    protected $_error_messages		= [];
    protected $_error_prefix		= '<p>';
    protected $_error_suffix		= '</p>';
    protected $error_string			= '';
    protected $_safe_form_data		= false;

    /**
     * Constructor
     */
    public function __construct(protected $_config_rules = [])
    {
        $this->CI =& get_instance();

        // Automatically load the form helper
        $this->CI->load->helper('form');

        // Set the character encoding in MB.
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding($this->CI->config->item('charset'));
        }

        log_message('debug', "Form Validation Class Initialized");
    }

    // --------------------------------------------------------------------

    /**
     * Set Rules
     *
     * This function takes an array of field names and validation
     * rules as input, validates the info, and stores it
     *
     * @access	public
     * @param	mixed
     * @param	string
     * @return	void
     */
    public function set_rules($field, $label = '', $rules = '')
    {
        // No reason to set rules if we have no POST data
        if (count($_POST) == 0) {
            return $this;
        }

        // If an array was passed via the first parameter instead of indidual string
        // values we cycle through it and recursively call this function.
        if (is_array($field)) {
            foreach ($field as $row) {
                // Houston, we have a problem...
                if (! isset($row['field']) or ! isset($row['rules'])) {
                    continue;
                }

                // If the field label wasn't passed we use the field name
                $label = (! isset($row['label'])) ? $row['field'] : $row['label'];

                // Here we go!
                $this->set_rules($row['field'], $label, $row['rules']);
            }
            return $this;
        }

        // No fields? Nothing to do...
        if (! is_string($field) or  ! is_string($rules) or $field == '') {
            return $this;
        }

        // If the field label wasn't passed we use the field name
        $label = ($label == '') ? $field : $label;

        // Is the field name an array?  We test for the existence of a bracket "[" in
        // the field name to determine this.  If it is an array, we break it apart
        // into its components so that we can fetch the corresponding POST data later
        if (str_contains($field, '[') and preg_match_all('/\[(.*?)\]/', $field, $matches)) {
            // Note: Due to a bug in current() that affects some versions
            // of PHP we can not pass function call directly into it
            $x = explode('[', $field);
            $indexes[] = current($x);

            for ($i = 0; $i < count($matches['0']); $i++) {
                if ($matches['1'][$i] != '') {
                    $indexes[] = $matches['1'][$i];
                }
            }

            $is_array = true;
        } else {
            $indexes	= [];
            $is_array	= false;
        }

        // Build our master array
        $this->_field_data[$field] = ['field'				=> $field, 'label'				=> $label, 'rules'				=> $rules, 'is_array'			=> $is_array, 'keys'				=> $indexes, 'postdata'			=> null, 'error'				=> ''];

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Set Error Message
     *
     * Lets users set their own error messages on the fly.  Note:  The key
     * name has to match the  function name that it corresponds to.
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	string
     */
    public function set_message($lang, $val = '')
    {
        if (! is_array($lang)) {
            $lang = [$lang => $val];
        }

        $this->_error_messages = array_merge($this->_error_messages, $lang);

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Set The Error Delimiter
     *
     * Permits a prefix/suffix to be added to each error message
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	void
     */
    public function set_error_delimiters($prefix = '<p>', $suffix = '</p>')
    {
        $this->_error_prefix = $prefix;
        $this->_error_suffix = $suffix;

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Get Error Message
     *
     * Gets the error message associated with a particular field
     *
     * @access	public
     * @param	string	the field name
     * @return	void
     */
    public function error($field = '', $prefix = '', $suffix = '')
    {
        if (! isset($this->_field_data[$field]['error']) or $this->_field_data[$field]['error'] == '') {
            return '';
        }

        if ($prefix == '') {
            $prefix = $this->_error_prefix;
        }

        if ($suffix == '') {
            $suffix = $this->_error_suffix;
        }

        return $prefix.$this->_field_data[$field]['error'].$suffix;
    }

    // --------------------------------------------------------------------

    /**
     * Error String
     *
     * Returns the error messages as a string, wrapped in the error delimiters
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	str
     */
    public function error_string($prefix = '', $suffix = '')
    {
        // No errrors, validation passes!
        if (count($this->_error_array) === 0) {
            return '';
        }

        if ($prefix == '') {
            $prefix = $this->_error_prefix;
        }

        if ($suffix == '') {
            $suffix = $this->_error_suffix;
        }

        // Generate the error string
        $str = '';
        foreach ($this->_error_array as $val) {
            if ($val != '') {
                $str .= $prefix.$val.$suffix."\n";
            }
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Run the Validator
     *
     * This function does all the work.
     *
     * @access	public
     * @return	bool
     */
    public function run($group = '')
    {
        // Do we even have any data to process?  Mm?
        if (count($_POST) == 0) {
            return false;
        }

        // Does the _field_data array containing the validation rules exist?
        // If not, we look to see if they were assigned via a config file
        if (count($this->_field_data) == 0) {
            // No validation rules?  We're done...
            if (count($this->_config_rules) == 0) {
                return false;
            }

            // Is there a validation rule for the particular URI being accessed?
            $uri = ($group == '') ? trim((string) $this->CI->uri->ruri_string(), '/') : $group;

            if ($uri != '' and isset($this->_config_rules[$uri])) {
                $this->set_rules($this->_config_rules[$uri]);
            } else {
                $this->set_rules($this->_config_rules);
            }

            // We're we able to set the rules correctly?
            if (count($this->_field_data) == 0) {
                log_message('debug', "Unable to find validation rules");
                return false;
            }
        }

        // Load the language file containing error messages
        $this->CI->lang->load('form_validation');

        // Cycle through the rules for each field, match the
        // corresponding $_POST item and test for errors
        foreach ($this->_field_data as $field => $row) {
            // Fetch the data from the corresponding $_POST array and cache it in the _field_data array.
            // Depending on whether the field name is an array or a string will determine where we get it from.

            if ($row['is_array'] == true) {
                $this->_field_data[$field]['postdata'] = $this->_reduce_array($_POST, $row['keys']);
            } else {
                if (isset($_POST[$field]) and $_POST[$field] != "") {
                    $this->_field_data[$field]['postdata'] = $_POST[$field];
                }
            }

            $this->_execute($row, explode('|', (string) $row['rules']), $this->_field_data[$field]['postdata']);
        }

        // Did we end up with any errors?
        $total_errors = count($this->_error_array);

        if ($total_errors > 0) {
            $this->_safe_form_data = true;
        }

        // Now we need to re-set the POST data with the new, processed data
        $this->_reset_post_array();

        // No errors, validation passes!
        if ($total_errors == 0) {
            return true;
        }

        // Validation fails
        return false;
    }

    // --------------------------------------------------------------------

    /**
     * Traverse a multidimensional $_POST array index until the data is found
     *
     * @access	private
     * @param	array
     * @param	array
     * @param	integer
     * @return	mixed
     */
    protected function _reduce_array($array, $keys, $i = 0)
    {
        if (is_array($array)) {
            if (isset($keys[$i])) {
                if (isset($array[$keys[$i]])) {
                    $array = $this->_reduce_array($array[$keys[$i]], $keys, ($i+1));
                } else {
                    return null;
                }
            } else {
                return $array;
            }
        }

        return $array;
    }

    // --------------------------------------------------------------------

    /**
     * Re-populate the _POST array with our finalized and processed data
     *
     * @access	private
     * @return	null
     */
    protected function _reset_post_array()
    {
        foreach ($this->_field_data as $field => $row) {
            if (! is_null($row['postdata'])) {
                if ($row['is_array'] == false) {
                    if (isset($_POST[$row['field']])) {
                        $_POST[$row['field']] = $this->prep_for_form($row['postdata']);
                    }
                } else {
                    // start with a reference
                    $post_ref =& $_POST;

                    // before we assign values, make a reference to the right POST key
                    if (count($row['keys']) == 1) {
                        $post_ref =& $post_ref[current($row['keys'])];
                    } else {
                        foreach ($row['keys'] as $val) {
                            $post_ref =& $post_ref[$val];
                        }
                    }

                    if (is_array($row['postdata'])) {
                        $array = [];
                        foreach ($row['postdata'] as $k => $v) {
                            $array[$k] = $this->prep_for_form($v);
                        }

                        $post_ref = $array;
                    } else {
                        $post_ref = $this->prep_for_form($row['postdata']);
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Executes the Validation routines
     *
     * @access	private
     * @param	array
     * @param	array
     * @param	mixed
     * @param	integer
     * @return	mixed
     */
    protected function _execute($row, $rules, $postdata = null, $cycles = 0)
    {
        // If the $_POST data is an array we will run a recursive call
        if (is_array($postdata)) {
            foreach ($postdata as $key => $val) {
                $this->_execute($row, $rules, $val, $cycles);
                $cycles++;
            }

            return;
        }

        // --------------------------------------------------------------------

        // If the field is blank, but NOT required, no further tests are necessary
        $callback = false;
        if (! in_array('required', $rules) and is_null($postdata)) {
            // Before we bail out, does the rule contain a callback?
            if (preg_match("/(callback_\w+(\[.*?\])?)/", implode(' ', $rules), $match)) {
                $callback = true;
                $rules = (['1' => $match[1]]);
            } else {
                return;
            }
        }

        // --------------------------------------------------------------------

        // Isset Test. Typically this rule will only apply to checkboxes.
        if (is_null($postdata) and $callback == false) {
            if (in_array('isset', $rules, true) or in_array('required', $rules)) {
                // Set the message type
                $type = (in_array('required', $rules)) ? 'required' : 'isset';

                if (! isset($this->_error_messages[$type])) {
                    if (false === ($line = $this->CI->lang->line($type))) {
                        $line = 'The field was not set';
                    }
                } else {
                    $line = $this->_error_messages[$type];
                }

                // Build the error message
                $message = sprintf($line, $this->_translate_fieldname($row['label']));

                // Save the error message
                $this->_field_data[$row['field']]['error'] = $message;

                if (! isset($this->_error_array[$row['field']])) {
                    $this->_error_array[$row['field']] = $message;
                }
            }

            return;
        }

        // --------------------------------------------------------------------

        // Cycle through each rule and run it
        foreach ($rules as $rule) {
            $_in_array = false;

            // We set the $postdata variable with the current data in our master array so that
            // each cycle of the loop is dealing with the processed data from the last cycle
            if ($row['is_array'] == true and is_array($this->_field_data[$row['field']]['postdata'])) {
                // We shouldn't need this safety, but just in case there isn't an array index
                // associated with this cycle we'll bail out
                if (! isset($this->_field_data[$row['field']]['postdata'][$cycles])) {
                    continue;
                }

                $postdata = $this->_field_data[$row['field']]['postdata'][$cycles];
                $_in_array = true;
            } else {
                $postdata = $this->_field_data[$row['field']]['postdata'];
            }

            // --------------------------------------------------------------------

            // Is the rule a callback?
            $callback = false;
            if (str_starts_with((string) $rule, 'callback_')) {
                $rule = substr((string) $rule, 9);
                $callback = true;
            }

            // Strip the parameter (if exists) from the rule
            // Rules can contain a parameter: max_length[5]
            $param = false;
            if (preg_match("/(.*?)\[(.*)\]/", (string) $rule, $match)) {
                $rule	= $match[1];
                $param	= $match[2];
            }

            // Call the function that corresponds to the rule
            if ($callback === true) {
                if (! method_exists($this->CI, $rule)) {
                    continue;
                }

                // Run the function and grab the result
                $result = $this->CI->$rule($postdata, $param);

                // Re-assign the result to the master data array
                if ($_in_array == true) {
                    $this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
                } else {
                    $this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                }

                // If the field isn't required and we just processed a callback we'll move on...
                if (! in_array('required', $rules, true) and $result !== false) {
                    continue;
                }
            } else {
                if (! method_exists($this, $rule)) {
                    // If our own wrapper function doesn't exist we see if a native PHP function does.
                    // Users can use any native PHP function call that has one param.
                    if (function_exists($rule)) {
                        $result = $rule($postdata);

                        if ($_in_array == true) {
                            $this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
                        } else {
                            $this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                        }
                    } else {
                        log_message('debug', "Unable to find validation rule: ".$rule);
                    }

                    continue;
                }

                $result = $this->$rule($postdata, $param);

                if ($_in_array == true) {
                    $this->_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
                } else {
                    $this->_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                }
            }

            // Did the rule test negatively?  If so, grab the error.
            if ($result === false) {
                if (! isset($this->_error_messages[$rule])) {
                    if (false === ($line = $this->CI->lang->line($rule))) {
                        $line = 'Unable to access an error message corresponding to your field name.';
                    }
                } else {
                    $line = $this->_error_messages[$rule];
                }

                // Is the parameter we are inserting into the error message the name
                // of another field?  If so we need to grab its "field label"
                if (isset($this->_field_data[$param]) and isset($this->_field_data[$param]['label'])) {
                    $param = $this->_translate_fieldname($this->_field_data[$param]['label']);
                }

                // Build the error message
                $message = sprintf($line, $this->_translate_fieldname($row['label']), $param);

                // Save the error message
                $this->_field_data[$row['field']]['error'] = $message;

                if (! isset($this->_error_array[$row['field']])) {
                    $this->_error_array[$row['field']] = $message;
                }

                return;
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Translate a field name
     *
     * @access	private
     * @param	string	the field name
     * @return	string
     */
    protected function _translate_fieldname($fieldname)
    {
        // Do we need to translate the field name?
        // We look for the prefix lang: to determine this
        if (str_starts_with((string) $fieldname, 'lang:')) {
            // Grab the variable
            $line = substr((string) $fieldname, 5);

            // Were we able to translate the field name?  If not we use $line
            if (false === ($fieldname = $this->CI->lang->line($line))) {
                return $line;
            }
        }

        return $fieldname;
    }

    // --------------------------------------------------------------------

    /**
     * Get the value from a form
     *
     * Permits you to repopulate a form field with the value it was submitted
     * with, or, if that value doesn't exist, with the default
     *
     * @access	public
     * @param	string	the field name
     * @param	string
     * @return	void
     */
    public function set_value($field = '', $default = '')
    {
        if (! isset($this->_field_data[$field])) {
            return $default;
        }

        // If the data is an array output them one at a time.
        //     E.g: form_input('name[]', set_value('name[]');
        if (is_array($this->_field_data[$field]['postdata'])) {
            return array_shift($this->_field_data[$field]['postdata']);
        }

        return $this->_field_data[$field]['postdata'];
    }

    // --------------------------------------------------------------------

    /**
     * Set Select
     *
     * Enables pull-down lists to be set to the value the user
     * selected in the event of an error
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	string
     */
    public function set_select($field = '', $value = '', $default = false)
    {
        if (! isset($this->_field_data[$field]) or ! isset($this->_field_data[$field]['postdata'])) {
            if ($default === true and count($this->_field_data) === 0) {
                return ' selected="selected"';
            }
            return '';
        }

        $field = $this->_field_data[$field]['postdata'];

        if (is_array($field)) {
            if (! in_array($value, $field)) {
                return '';
            }
        } else {
            if (($field == '' or $value == '') or ($field != $value)) {
                return '';
            }
        }

        return ' selected="selected"';
    }

    // --------------------------------------------------------------------

    /**
     * Set Radio
     *
     * Enables radio buttons to be set to the value the user
     * selected in the event of an error
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	string
     */
    public function set_radio($field = '', $value = '', $default = false)
    {
        if (! isset($this->_field_data[$field]) or ! isset($this->_field_data[$field]['postdata'])) {
            if ($default === true and count($this->_field_data) === 0) {
                return ' checked="checked"';
            }
            return '';
        }

        $field = $this->_field_data[$field]['postdata'];

        if (is_array($field)) {
            if (! in_array($value, $field)) {
                return '';
            }
        } else {
            if (($field == '' or $value == '') or ($field != $value)) {
                return '';
            }
        }

        return ' checked="checked"';
    }

    // --------------------------------------------------------------------

    /**
     * Set Checkbox
     *
     * Enables checkboxes to be set to the value the user
     * selected in the event of an error
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	string
     */
    public function set_checkbox($field = '', $value = '', $default = false)
    {
        if (! isset($this->_field_data[$field]) or ! isset($this->_field_data[$field]['postdata'])) {
            if ($default === true and count($this->_field_data) === 0) {
                return ' checked="checked"';
            }
            return '';
        }

        $field = $this->_field_data[$field]['postdata'];

        if (is_array($field)) {
            if (! in_array($value, $field)) {
                return '';
            }
        } else {
            if (($field == '' or $value == '') or ($field != $value)) {
                return '';
            }
        }

        return ' checked="checked"';
    }

    // --------------------------------------------------------------------

    /**
     * Required
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function required($str)
    {
        if (! is_array($str)) {
            return (trim((string) $str) == '') ? false : true;
        } else {
            return (! empty($str));
        }
    }

    // --------------------------------------------------------------------

    /**
     * Performs a Regular Expression match test.
     *
     * @access	public
     * @param	string
     * @param	regex
     * @return	bool
     */
    public function regex_match($str, $regex)
    {
        if (! preg_match($regex, (string) $str)) {
            return false;
        }

        return  true;
    }

    // --------------------------------------------------------------------

    /**
     * Match one field to another
     *
     * @access	public
     * @param	string
     * @param	field
     * @return	bool
     */
    public function matches($str, $field)
    {
        if (! isset($_POST[$field])) {
            return false;
        }

        $field = $_POST[$field];

        return ($str !== $field) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Match one field to another
     *
     * @access	public
     * @param	string
     * @param	field
     * @return	bool
     */
    public function is_unique($str, $field)
    {
        [$table, $field]=explode('.', (string) $field);
        $query = $this->CI->db->limit(1)->get_where($table, [$field => $str]);

        return $query->num_rows() === 0;
    }

    // --------------------------------------------------------------------

    /**
     * Minimum Length
     *
     * @access	public
     * @param	string
     * @param	value
     * @return	bool
     */
    public function min_length($str, $val)
    {
        if (preg_match("/[^0-9]/", (string) $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            return (mb_strlen((string) $str) < $val) ? false : true;
        }

        return (strlen((string) $str) < $val) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Max Length
     *
     * @access	public
     * @param	string
     * @param	value
     * @return	bool
     */
    public function max_length($str, $val)
    {
        if (preg_match("/[^0-9]/", (string) $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            return (mb_strlen((string) $str) > $val) ? false : true;
        }

        return (strlen((string) $str) > $val) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Exact Length
     *
     * @access	public
     * @param	string
     * @param	value
     * @return	bool
     */
    public function exact_length($str, $val)
    {
        if (preg_match("/[^0-9]/", (string) $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            return (mb_strlen((string) $str) != $val) ? false : true;
        }

        return (strlen((string) $str) != $val) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Valid Email
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function valid_email($str)
    {
        return (! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", (string) $str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Valid Emails
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function valid_emails($str)
    {
        if (!str_contains((string) $str, ',')) {
            return $this->valid_email(trim((string) $str));
        }

        foreach (explode(',', (string) $str) as $email) {
            if (trim($email) != '' && $this->valid_email(trim($email)) === false) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Validate IP Address
     *
     * @access	public
     * @param	string
     * @param	string "ipv4" or "ipv6" to validate a specific ip format
     * @return	string
     */
    public function valid_ip($ip, $which = '')
    {
        return $this->CI->input->valid_ip($ip, $which);
    }

    // --------------------------------------------------------------------

    /**
     * Alpha
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function alpha($str)
    {
        return (! preg_match("/^([a-z])+$/i", (string) $str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Alpha-numeric
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function alpha_numeric($str)
    {
        return (! preg_match("/^([a-z0-9])+$/i", (string) $str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Alpha-numeric with underscores and dashes
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function alpha_dash($str)
    {
        return (! preg_match("/^([-a-z0-9_-])+$/i", (string) $str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Numeric
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function numeric($str)
    {
        return (bool)preg_match('/^[\-+]?[0-9]*\.?[0-9]+$/', (string) $str);
    }

    // --------------------------------------------------------------------

    /**
     * Is Numeric
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function is_numeric($str)
    {
        return (! is_numeric($str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Integer
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function integer($str)
    {
        return (bool) preg_match('/^[\-+]?[0-9]+$/', (string) $str);
    }

    // --------------------------------------------------------------------

    /**
     * Decimal number
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function decimal($str)
    {
        return (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', (string) $str);
    }

    // --------------------------------------------------------------------

    /**
     * Greather than
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function greater_than($str, $min)
    {
        if (! is_numeric($str)) {
            return false;
        }
        return $str > $min;
    }

    // --------------------------------------------------------------------

    /**
     * Less than
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function less_than($str, $max)
    {
        if (! is_numeric($str)) {
            return false;
        }
        return $str < $max;
    }

    // --------------------------------------------------------------------

    /**
     * Is a Natural number  (0,1,2,3, etc.)
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function is_natural($str)
    {
        return (bool) preg_match('/^[0-9]+$/', (string) $str);
    }

    // --------------------------------------------------------------------

    /**
     * Is a Natural number, but not a zero  (1,2,3, etc.)
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function is_natural_no_zero($str)
    {
        if (! preg_match('/^[0-9]+$/', (string) $str)) {
            return false;
        }

        if ($str == 0) {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Valid Base64
     *
     * Tests a string for characters outside of the Base64 alphabet
     * as defined by RFC 2045 http://www.faqs.org/rfcs/rfc2045
     *
     * @access	public
     * @param	string
     * @return	bool
     */
    public function valid_base64($str)
    {
        return (bool) ! preg_match('/[^a-zA-Z0-9\/\+=]/', (string) $str);
    }

    // --------------------------------------------------------------------

    /**
     * Prep data for form
     *
     * This function allows HTML to be safely shown in a form.
     * Special characters are converted.
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function prep_for_form($data = '')
    {
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->prep_for_form($val);
            }

            return $data;
        }

        if ($this->_safe_form_data == false or $data === '') {
            return $data;
        }

        return str_replace(["'", '"', '<', '>'], ["&#39;", "&quot;", '&lt;', '&gt;'], stripslashes((string) $data));
    }

    // --------------------------------------------------------------------

    /**
     * Prep URL
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function prep_url($str = '')
    {
        if ($str == 'http://' or $str == '') {
            return '';
        }

        if (!str_starts_with((string) $str, 'http://') && !str_starts_with((string) $str, 'https://')) {
            $str = 'http://'.$str;
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Strip Image Tags
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function strip_image_tags($str)
    {
        return $this->CI->input->strip_image_tags($str);
    }

    // --------------------------------------------------------------------

    /**
     * XSS Clean
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function xss_clean($str)
    {
        return $this->CI->security->xss_clean($str);
    }

    // --------------------------------------------------------------------

    /**
     * Convert PHP tags to entities
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function encode_php_tags($str)
    {
        return str_replace(['<?php', '<?PHP', '<?', '?>'], ['&lt;?php', '&lt;?PHP', '&lt;?', '?&gt;'], $str);
    }
}
// END Form Validation Class

/* End of file Form_validation.php */
/* Location: ./system/libraries/Form_validation.php */
