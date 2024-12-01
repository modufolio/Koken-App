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
 * CodeIgniter Encryption Class
 *
 * Provides two-way keyed encryption via PHP's MCrypt and/or OpenSSL extensions.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/encryption.html
 */
class CI_Encrypt
{
    public $CI;
    public $encryption_key	= '';
    public $_hash_type	= 'sha1';
    public $_mcrypt_cipher;
    public $_mcrypt_mode;

    protected $_driver;
    protected $_drivers = [];
    protected $_cipher = 'aes-128';
    protected $_mode = 'cbc';
    protected $_handle;

    /**
     * Constructor
     *
     * Simply determines whether the mcrypt library exists.
     *
     */
    public function __construct()
    {
        $this->CI =& get_instance();

        $this->_drivers = ['mcrypt'  => false, 'openssl' => extension_loaded('openssl')];

        if (! $this->_drivers['mcrypt'] && ! $this->_drivers['openssl']) {
            show_error('Encryption: Unable to find an available encryption driver.');
        }

        $this->_driver = ($this->_drivers['mcrypt'] === true)
            ? 'mcrypt'
            : 'openssl';

        log_message('debug', "Encryption: Auto-configured driver '".$this->_driver."'.");

        if ($this->_driver === 'openssl') {
            $this->_openssl_initialize();
        }

        log_message('debug', "Encrypt Class Initialized");
    }

    // --------------------------------------------------------------------

    /**
     * Initialize OpenSSL
     *
     * @param	array	$params	Configuration parameters
     * @return	void
     */
    protected function _openssl_initialize()
    {
        if (isset($this->_cipher, $this->_mode)) {
            // This is mostly for the stream mode, which doesn't get suffixed in OpenSSL
            $handle = empty($this->_mode)
                ? $this->_cipher
                : $this->_cipher.'-'.$this->_mode;

            if (! in_array($handle, openssl_get_cipher_methods(), true)) {
                $this->_handle = null;
                log_message('error', 'Encryption: Unable to initialize OpenSSL with method '.strtoupper((string) $handle).'.');
            } else {
                $this->_handle = $handle;
                log_message('info', 'Encryption: OpenSSL initialized with method '.strtoupper($handle).'.');
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the encryption key
     *
     * Returns it as MD5 in order to have an exact-length 128 bit key.
     * Mcrypt is sensitive to keys that are not the correct length
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function get_key($key = '')
    {
        if ($key == '') {
            if ($this->encryption_key != '') {
                return $this->encryption_key;
            }

            $CI =& get_instance();
            $key = $CI->config->item('encryption_key');

            if ($key == false) {
                show_error('In order to use the encryption class requires that you set an encryption key in your config file.');
            }
        }

        return md5((string) $key);
    }

    // --------------------------------------------------------------------

    /**
     * Set the encryption key
     *
     * @access	public
     * @param	string
     * @return	void
     */
    public function set_key($key = '')
    {
        $this->encryption_key = $key;
    }

    // --------------------------------------------------------------------

    /**
     * Encode
     *
     * Encodes the message string using bitwise XOR encoding.
     * The key is combined with a random hash, and then it
     * too gets converted using XOR. The whole thing is then run
     * through mcrypt using the randomized key. The end result
     * is a double-encrypted message string that is randomized
     * with each call to this function, even if the supplied
     * message and key are the same.
     *
     * @access	public
     * @param	string	the string to encode
     * @param	string	the key
     * @return	string
     */
    public function encode($string, $key = '')
    {
        $key = $this->get_key($key);
        $enc = $this->{$this->_driver.'_encode'}($string, $key);

        return base64_encode((string) $enc);
    }

    // --------------------------------------------------------------------

    /**
     * Decode
     *
     * Reverses the above process
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	string
     */
    public function decode($string, $key = '')
    {
        $key = $this->get_key($key);

        if (preg_match('/[^a-zA-Z0-9\/\+=]/', (string) $string)) {
            return false;
        }

        $dec = base64_decode((string) $string);

        if (($dec = $this->{$this->_driver.'_decode'}($dec, $key)) === false) {
            return false;
        }

        return $dec;
    }

    // --------------------------------------------------------------------

    /**
     * XOR Decode
     *
     * Takes an encoded string and key as input and generates the
     * plain-text original message
     *
     * @access	private
     * @param	string
     * @param	string
     * @return	string
     */
    public function _xor_decode($string, $key)
    {
        $string = $this->_xor_merge($string, $key);

        $dec = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $dec .= (substr($string, $i++, 1) ^ substr($string, $i, 1));
        }

        return $dec;
    }

    // --------------------------------------------------------------------

    /**
     * XOR key + string Combiner
     *
     * Takes a string and key as input and computes the difference using XOR
     *
     * @access	private
     * @param	string
     * @param	string
     * @return	string
     */
    public function _xor_merge($string, $key)
    {
        $hash = $this->hash($key);
        $str = '';
        for ($i = 0; $i < strlen((string) $string); $i++) {
            $str .= substr((string) $string, $i, 1) ^ substr($hash, ($i % strlen($hash)), 1);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Create a random key
     *
     * @param	int	$length	Output length
     * @return	string
     */
    public function create_key($length)
    {
        if (function_exists('random_bytes')) {
            try {
                return random_bytes((int) $length);
            } catch (Exception $e) {
                log_message('error', $e->getMessage());
                return false;
            }
        } elseif (defined('MCRYPT_DEV_URANDOM')) {
            return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        }

        $is_secure = null;
        $key = openssl_random_pseudo_bytes($length, $is_secure);
        return ($is_secure === true)
            ? $key
            : false;
    }

    // --------------------------------------------------------------------

    // --------------------------------------------------------------------

    /**
     * Encrypt via OpenSSL
     *
     * @param	string	$data	Input data
     * @param	array	$params	Input parameters
     * @return	string
     */
    protected function openssl_encode($data, $key)
    {
        if (empty($this->_handle)) {
            return false;
        }

        $iv = ($iv_size = openssl_cipher_iv_length($this->_handle))
            ? $this->create_key($iv_size)
            : null;

        $data = openssl_encrypt(
            $data,
            $this->_handle,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($data === false) {
            return false;
        }

        return $iv.$data;
    }

    // --------------------------------------------------------------------

    // HACK
    public function _strlen($str)
    {
        return function_exists('mb_strlen') ? mb_strlen((string) $str, 'latin1') : strlen((string) $str);
    }

    public function _substr($str, $start, $end = null)
    {
        if (function_exists('mb_substr')) {
            return mb_substr((string) $str, $start, $end, 'latin1');
        }

        return substr((string) $str, $start, $end);
    }

    // --------------------------------------------------------------------

    /**
     * Decrypt via OpenSSL
     *
     * @param	string	$data	Encrypted data
     * @param	array	$params	Input parameters
     * @return	string
     */
    protected function openssl_decode($data, $key)
    {
        if ($iv_size = openssl_cipher_iv_length($this->_handle)) {
            $iv = $this->_substr($data, 0, $iv_size);
            $data = $this->_substr($data, $iv_size);
        } else {
            $iv = null;
        }

        return empty($this->_handle)
            ? false
            : openssl_decrypt(
                $data,
                $this->_handle,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
    }

    // --------------------------------------------------------------------


    /**
     * Adds permuted noise to the IV + encrypted data to protect
     * against Man-in-the-middle attacks on CBC mode ciphers
     * http://www.ciphersbyritter.com/GLOSSARY.HTM#IV
     *
     * Function description
     *
     * @access	private
     * @param	string
     * @param	string
     * @return	string
     */
    public function _add_cipher_noise($data, $key)
    {
        $keyhash = $this->hash($key);
        $keylen = $this->_strlen($keyhash);
        $str = '';

        for ($i = 0, $j = 0, $len = $this->_strlen($data); $i < $len; ++$i, ++$j) {
            if ($j >= $keylen) {
                $j = 0;
            }

            $str .= chr((ord($data[$i]) + ord($keyhash[$j])) % 256);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Removes permuted noise from the IV + encrypted data, reversing
     * _add_cipher_noise()
     *
     * Function description
     *
     * @access	public
     * @param	type
     * @return	type
     */
    public function _remove_cipher_noise($data, $key)
    {
        $keyhash = $this->hash($key);
        $keylen = $this->_strlen($keyhash);
        $str = '';

        for ($i = 0, $j = 0, $len = $this->_strlen($data); $i < $len; ++$i, ++$j) {
            if ($j >= $keylen) {
                $j = 0;
            }

            $temp = ord($data[$i]) - ord($keyhash[$j]);

            if ($temp < 0) {
                $temp = $temp + 256;
            }

            $str .= chr($temp);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Set the Mcrypt Cipher
     *
     * @access	public
     * @param	constant
     * @return	string
     */
    public function set_mcrypt_cipher($cipher)
    {
        $this->_mcrypt_cipher = $cipher;
    }

    // --------------------------------------------------------------------

    /**
     * Set the Mcrypt Mode
     *
     * @access	public
     * @param	constant
     * @return	string
     */
    public function set_mcrypt_mode($mode)
    {
        $this->_mcrypt_mode = $mode;
    }

    // --------------------------------------------------------------------

    /**
     * Get Mcrypt cipher Value
     *
     * @access	private
     * @return	string
     */
    public function _get_mcrypt_cipher()
    {
        if ($this->_mcrypt_cipher == '') {
            $this->_mcrypt_cipher = MCRYPT_RIJNDAEL_256;
        }

        return $this->_mcrypt_cipher;
    }

    // --------------------------------------------------------------------

    /**
     * Get Mcrypt Mode Value
     *
     * @access	private
     * @return	string
     */
    public function _get_mcrypt_mode()
    {
        if ($this->_mcrypt_mode == '') {
            $this->_mcrypt_mode = MCRYPT_MODE_CBC;
        }

        return $this->_mcrypt_mode;
    }

    // --------------------------------------------------------------------

    /**
     * Set the Hash type
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function set_hash($type = 'sha1')
    {
        $this->_hash_type = ($type != 'sha1' and $type != 'md5') ? 'sha1' : $type;
    }

    // --------------------------------------------------------------------

    /**
     * Hash encode a string
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function hash($str)
    {
        return ($this->_hash_type == 'sha1') ? $this->sha1($str) : md5((string) $str);
    }

    // --------------------------------------------------------------------

    /**
     * Generate an SHA1 Hash
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function sha1($str)
    {
        if (! function_exists('sha1')) {
            if (! function_exists('mhash')) {
                require_once(BASEPATH.'libraries/Sha1.php');
                $SH = new CI_SHA();
                return $SH->generate($str);
            } else {
                return bin2hex(mhash(MHASH_SHA1, $str));
            }
        } else {
            return sha1((string) $str);
        }
    }
}

// END CI_Encrypt class

/* End of file Encrypt.php */
/* Location: ./system/libraries/Encrypt.php */
