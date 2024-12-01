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

if (! function_exists('xml_parser_create')) {
    show_error('Your PHP installation does not support XML');
}

if (! class_exists('CI_Xmlrpc')) {
    show_error('You must load the Xmlrpc class before loading the Xmlrpcs class in order to create a server.');
}

// ------------------------------------------------------------------------

/**
 * XML-RPC server class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	XML-RPC
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/xmlrpc.html
 */
class CI_Xmlrpcs extends CI_Xmlrpc
{
    public $methods		= [];	//array of methods mapped to function names and signatures
    public $debug_msg		= '';		// Debug Message
    public $system_methods = [];	// XML RPC Server methods
    public $controller_obj;

    public $object			= false;

    /**
     * Constructor
     */
    public function __construct($config=[])
    {
        parent::__construct();
        $this->set_system_methods();

        if (isset($config['functions']) && is_array($config['functions'])) {
            $this->methods = array_merge($this->methods, $config['functions']);
        }

        log_message('debug', "XML-RPC Server Class Initialized");
    }

    // --------------------------------------------------------------------

    /**
     * Initialize Prefs and Serve
     *
     * @access	public
     * @param	mixed
     * @return	void
     */
    #[\Override]
    public function initialize($config=[])
    {
        if (isset($config['functions']) && is_array($config['functions'])) {
            $this->methods = array_merge($this->methods, $config['functions']);
        }

        if (isset($config['debug'])) {
            $this->debug = $config['debug'];
        }

        if (isset($config['object']) && is_object($config['object'])) {
            $this->object = $config['object'];
        }

        if (isset($config['xss_clean'])) {
            $this->xss_clean = $config['xss_clean'];
        }
    }

    // --------------------------------------------------------------------

    /**
     * Setting of System Methods
     *
     * @access	public
     * @return	void
     */
    public function set_system_methods()
    {
        $this->methods = ['system.listMethods'	 => ['function' => 'this.listMethods', 'signature' => [[$this->xmlrpcArray, $this->xmlrpcString], [$this->xmlrpcArray]], 'docstring' => 'Returns an array of available methods on this server'], 'system.methodHelp'		 => ['function' => 'this.methodHelp', 'signature' => [[$this->xmlrpcString, $this->xmlrpcString]], 'docstring' => 'Returns a documentation string for the specified method'], 'system.methodSignature' => ['function' => 'this.methodSignature', 'signature' => [[$this->xmlrpcArray, $this->xmlrpcString]], 'docstring' => 'Returns an array describing the return type and required parameters of a method'], 'system.multicall'		 => ['function' => 'this.multicall', 'signature' => [[$this->xmlrpcArray, $this->xmlrpcArray]], 'docstring' => 'Combine multiple RPC calls in one request. See http://www.xmlrpc.com/discuss/msgReader$1208 for details']];
    }

    // --------------------------------------------------------------------

    /**
     * Main Server Function
     *
     * @access	public
     * @return	void
     */
    public function serve(): never
    {
        $r = $this->parseRequest();
        $payload  = '<?xml version="1.0" encoding="'.$this->xmlrpc_defencoding.'"?'.'>'."\n";
        $payload .= $this->debug_msg;
        $payload .= $r->prepare_response();

        header("Content-Type: text/xml");
        header("Content-Length: ".strlen($payload));
        exit($payload);
    }

    // --------------------------------------------------------------------

    /**
     * Add Method to Class
     *
     * @access	public
     * @param	string	method name
     * @param	string	function
     * @param	string	signature
     * @param	string	docstring
     * @return	void
     */
    public function add_to_map($methodname, $function, $sig, $doc)
    {
        $this->methods[$methodname] = ['function'  => $function, 'signature' => $sig, 'docstring' => $doc];
    }

    // --------------------------------------------------------------------

    /**
     * Parse Server Request
     *
     * @access	public
     * @param	string	data
     * @return	object	xmlrpc response
     */
    public function parseRequest($data='')
    {
        global $HTTP_RAW_POST_DATA;

        //-------------------------------------
        //  Get Data
        //-------------------------------------

        if ($data == '') {
            $data = $HTTP_RAW_POST_DATA;
        }

        //-------------------------------------
        //  Set up XML Parser
        //-------------------------------------

        $parser = xml_parser_create($this->xmlrpc_defencoding);
        $parser_object = new XML_RPC_Message("filler");

        $parser_object->xh[$parser]					= [];
        $parser_object->xh[$parser]['isf']			= 0;
        $parser_object->xh[$parser]['isf_reason']	= '';
        $parser_object->xh[$parser]['params']		= [];
        $parser_object->xh[$parser]['stack']		= [];
        $parser_object->xh[$parser]['valuestack']	= [];
        $parser_object->xh[$parser]['method']		= '';

        xml_set_object($parser, $parser_object);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, true);
        xml_set_element_handler($parser, 'open_tag', 'closing_tag');
        xml_set_character_data_handler($parser, 'character_data');
        //xml_set_default_handler($parser, 'default_handler');


        //-------------------------------------
        //  PARSE + PROCESS XML DATA
        //-------------------------------------

        if (! xml_parse($parser, (string) $data, 1)) {
            // return XML error as a faultCode
            $r = new XML_RPC_Response(
                0,
                $this->xmlrpcerrxml + xml_get_error_code($parser),
                sprintf(
                'XML error: %s at line %d',
                xml_error_string(xml_get_error_code($parser)),
                xml_get_current_line_number($parser)
            )
            );
            xml_parser_free($parser);
        } elseif ($parser_object->xh[$parser]['isf']) {
            return new XML_RPC_Response(0, $this->xmlrpcerr['invalid_return'], $this->xmlrpcstr['invalid_return']);
        } else {
            xml_parser_free($parser);

            $m = new XML_RPC_Message($parser_object->xh[$parser]['method']);
            $plist='';

            for ($i=0; $i < count($parser_object->xh[$parser]['params']); $i++) {
                if ($this->debug === true) {
                    $plist .= "$i - " .  print_r(get_object_vars($parser_object->xh[$parser]['params'][$i]), true). ";\n";
                }

                $m->addParam($parser_object->xh[$parser]['params'][$i]);
            }

            if ($this->debug === true) {
                echo "<pre>";
                echo "---PLIST---\n" . $plist . "\n---PLIST END---\n\n";
                echo "</pre>";
            }

            $r = $this->_execute($m);
        }

        //-------------------------------------
        //  SET DEBUGGING MESSAGE
        //-------------------------------------

        if ($this->debug === true) {
            $this->debug_msg = "<!-- DEBUG INFO:\n\n".$plist."\n END DEBUG-->\n";
        }

        return $r;
    }

    // --------------------------------------------------------------------

    /**
     * Executes the Method
     *
     * @access	protected
     * @param	object
     * @return	mixed
     */
    public function _execute($m)
    {
        $methName = $m->method_name;

        // Check to see if it is a system call
        $system_call = (strncmp($methName, 'system', 5) == 0) ? true : false;

        if ($this->xss_clean == false) {
            $m->xss_clean = false;
        }

        //-------------------------------------
        //  Valid Method
        //-------------------------------------

        if (! isset($this->methods[$methName]['function'])) {
            return new XML_RPC_Response(0, $this->xmlrpcerr['unknown_method'], $this->xmlrpcstr['unknown_method']);
        }

        //-------------------------------------
        //  Check for Method (and Object)
        //-------------------------------------

        $method_parts = explode(".", (string) $this->methods[$methName]['function']);
        $objectCall = (isset($method_parts['1']) && $method_parts['1'] != "") ? true : false;

        if ($system_call === true) {
            if (! is_callable([$this, $method_parts['1']])) {
                return new XML_RPC_Response(0, $this->xmlrpcerr['unknown_method'], $this->xmlrpcstr['unknown_method']);
            }
        } else {
            if ($objectCall && ! is_callable([$method_parts['0'], $method_parts['1']])) {
                return new XML_RPC_Response(0, $this->xmlrpcerr['unknown_method'], $this->xmlrpcstr['unknown_method']);
            } elseif (! $objectCall && ! is_callable($this->methods[$methName]['function'])) {
                return new XML_RPC_Response(0, $this->xmlrpcerr['unknown_method'], $this->xmlrpcstr['unknown_method']);
            }
        }

        //-------------------------------------
        //  Checking Methods Signature
        //-------------------------------------

        if (isset($this->methods[$methName]['signature'])) {
            $sig = $this->methods[$methName]['signature'];
            for ($i=0; $i<count($sig); $i++) {
                $current_sig = $sig[$i];

                if (count($current_sig) == count($m->params)+1) {
                    for ($n=0; $n < count($m->params); $n++) {
                        $p = $m->params[$n];
                        $pt = ($p->kindOf() == 'scalar') ? $p->scalarval() : $p->kindOf();

                        if ($pt != $current_sig[$n+1]) {
                            $pno = $n+1;
                            $wanted = $current_sig[$n+1];

                            return new XML_RPC_Response(
                                0,
                                $this->xmlrpcerr['incorrect_params'],
                                $this->xmlrpcstr['incorrect_params'] .
                                ": Wanted {$wanted}, got {$pt} at param {$pno})"
                            );
                        }
                    }
                }
            }
        }

        //-------------------------------------
        //  Calls the Function
        //-------------------------------------

        if ($objectCall === true) {
            if ($method_parts[0] == "this" && $system_call == true) {
                return call_user_func([$this, $method_parts[1]], $m);
            } else {
                if ($this->object === false) {
                    $CI =& get_instance();
                    return $CI->$method_parts['1']($m);
                } else {
                    return $this->object->$method_parts['1']($m);
                    //return call_user_func(array(&$method_parts['0'],$method_parts['1']), $m);
                }
            }
        } else {
            return call_user_func($this->methods[$methName]['function'], $m);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Server Function:  List Methods
     *
     * @access	public
     * @param	mixed
     * @return	object
     */
    public function listMethods($m)
    {
        $v = new XML_RPC_Values();
        $output = [];

        foreach ($this->methods as $key => $value) {
            $output[] = new XML_RPC_Values($key, 'string');
        }

        foreach ($this->system_methods as $key => $value) {
            $output[]= new XML_RPC_Values($key, 'string');
        }

        $v->addArray($output);
        return new XML_RPC_Response($v);
    }

    // --------------------------------------------------------------------

    /**
     * Server Function:  Return Signature for Method
     *
     * @access	public
     * @param	mixed
     * @return	object
     */
    public function methodSignature($m)
    {
        $parameters = $m->output_parameters();
        $method_name = $parameters[0];

        if (isset($this->methods[$method_name])) {
            if ($this->methods[$method_name]['signature']) {
                $sigs = [];
                $signature = $this->methods[$method_name]['signature'];

                for ($i=0; $i < count($signature); $i++) {
                    $cursig = [];
                    $inSig = $signature[$i];
                    for ($j=0; $j<count($inSig); $j++) {
                        $cursig[]= new XML_RPC_Values($inSig[$j], 'string');
                    }
                    $sigs[]= new XML_RPC_Values($cursig, 'array');
                }
                $r = new XML_RPC_Response(new XML_RPC_Values($sigs, 'array'));
            } else {
                $r = new XML_RPC_Response(new XML_RPC_Values('undef', 'string'));
            }
        } else {
            $r = new XML_RPC_Response(0, $this->xmlrpcerr['introspect_unknown'], $this->xmlrpcstr['introspect_unknown']);
        }
        return $r;
    }

    // --------------------------------------------------------------------

    /**
     * Server Function:  Doc String for Method
     *
     * @access	public
     * @param	mixed
     * @return	object
     */
    public function methodHelp($m)
    {
        $parameters = $m->output_parameters();
        $method_name = $parameters[0];

        if (isset($this->methods[$method_name])) {
            $docstring = $this->methods[$method_name]['docstring'] ?? '';

            return new XML_RPC_Response(new XML_RPC_Values($docstring, 'string'));
        } else {
            return new XML_RPC_Response(0, $this->xmlrpcerr['introspect_unknown'], $this->xmlrpcstr['introspect_unknown']);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Server Function:  Multi-call
     *
     * @access	public
     * @param	mixed
     * @return	object
     */
    public function multicall($m)
    {
        // Disabled
        return new XML_RPC_Response(0, $this->xmlrpcerr['unknown_method'], $this->xmlrpcstr['unknown_method']);

        $parameters = $m->output_parameters();
        $calls = $parameters[0];

        $result = [];

        foreach ($calls as $value) {
            //$attempt = $this->_execute(new XML_RPC_Message($value[0], $value[1]));

            $m = new XML_RPC_Message($value[0]);
            $plist='';

            for ($i=0; $i < count($value[1]); $i++) {
                $m->addParam(new XML_RPC_Values($value[1][$i], 'string'));
            }

            $attempt = $this->_execute($m);

            if ($attempt->faultCode() != 0) {
                return $attempt;
            }

            $result[] = new XML_RPC_Values([$attempt->value()], 'array');
        }

        return new XML_RPC_Response(new XML_RPC_Values($result, 'array'));
    }

    // --------------------------------------------------------------------

    /**
     *  Multi-call Function:  Error Handling
     *
     * @access	public
     * @param	mixed
     * @return	object
     */
    public function multicall_error($err)
    {
        $str  = is_string($err) ? $this->xmlrpcstr["multicall_{$err}"] : $err->faultString();
        $code = is_string($err) ? $this->xmlrpcerr["multicall_{$err}"] : $err->faultCode();

        $struct['faultCode'] = new XML_RPC_Values($code, 'int');
        $struct['faultString'] = new XML_RPC_Values($str, 'string');

        return new XML_RPC_Values($struct, 'struct');
    }

    // --------------------------------------------------------------------

    /**
     *  Multi-call Function:  Processes method
     *
     * @access	public
     * @param	mixed
     * @return	object
     */
    public function do_multicall($call)
    {
        if ($call->kindOf() != 'struct') {
            return $this->multicall_error('notstruct');
        } elseif (! $methName = $call->me['struct']['methodName']) {
            return $this->multicall_error('nomethod');
        }
        $scalar_type = key($methName->me);
        $scalar_value = current($methName->me);
        next($methName->me);
        $scalar_type = $scalar_type == $this->xmlrpcI4 ? $this->xmlrpcInt : $scalar_type;

        if ($methName->kindOf() != 'scalar' or $scalar_type != 'string') {
            return $this->multicall_error('notstring');
        } elseif ($scalar_value == 'system.multicall') {
            return $this->multicall_error('recursion');
        } elseif (! $params = $call->me['struct']['params']) {
            return $this->multicall_error('noparams');
        } elseif ($params->kindOf() != 'array') {
            return $this->multicall_error('notarray');
        }
        $a = key($params->me);
        $b = current($params->me);
        next($params->me);
        $numParams = count($b);

        $msg = new XML_RPC_Message($scalar_value);
        for ($i = 0; $i < $numParams; $i++) {
            $msg->params[] = $params->me['array'][$i];
        }

        $result = $this->_execute($msg);

        if ($result->faultCode() != 0) {
            return $this->multicall_error($result);
        }

        return new XML_RPC_Values([$result->value()], 'array');
    }
}
// END XML_RPC_Server class


/* End of file Xmlrpcs.php */
/* Location: ./system/libraries/Xmlrpcs.php */
