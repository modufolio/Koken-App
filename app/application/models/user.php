<?php

class User extends DataMapper
{
    /**
     * Constructor: calls parent constructor
     */
    public function __construct($id = null)
    {
        parent::__construct($id);
        $this->load->library('hash');
    }

    public $has_many = array(
        'content' => array(
            'class' => 'content',
            'other_field' => 'created_by'
        ),
        'application',
        'history'
    );

    public $validation = array(
        'internal_id' => array(
            'label' => 'Internal id',
            'rules' => array('internalize', 'required')
        ),
        'password' => array(
            'label' => 'password',
            'rules' => array('hash_password', 'required')
        ),
        'public_first_name' => array(
            'rules' => array('fallback')
        ),
        'public_last_name' => array(
            'rules' => array('fallback'),
        ),
        'public_email' => array(
            'rules' => array('fallback'),
        ),
    );

    public function _fallback($field)
    {
        if (empty($this->{$field})) {
            $backup = str_replace('public_', '', $field);
            $this->{$field} = $this->{$backup};
        }
    }

    /**
     * Create internal ID if one is not present
     */
    public function _internalize($field)
    {
        $backups = array('public_first_name', 'public_last_name', 'public_email');
        foreach ($backups as $backup) {
            $this->_fallback($backup);
        }
        $this->{$field} = koken_rand();
    }

    public function _hash_password($field)
    {
        $this->{$field} = Hash::HashPassword($this->{$field});
    }

    public function check_password($provided_password)
    {
        return Hash::CheckPassword($provided_password, $this->password);
    }

    public function to_array($options = array())
    {
        $this->externals = !empty($this->externals) ? unserialize($this->externals) : null;
        if (!$this->externals) {
            $this->externals = null;
        }
        $exclude = array('password', 'news', 'help', 'focal_point', 'theme');
        $bools = [];
        list($data, $public_fields) = $this->prepare_for_output($options, $exclude, $bools);
        return $data;
    }

    public function create_session($session, $remember = false)
    {
        $a = new Application();
        $a->user_id = $this->id;
        $a->token = koken_rand();
        $a->role = 'god';
        $a->save();

        $session->set_userdata(array(
            'token' => $a->token,
            'user' => $this->to_array(),
        ));

        if ($remember) {
            $token = koken_rand();
            $this->remember_me = $token;
            $this->save();

            $this->load->helper('cookie');

            set_cookie(array(
                'name' => 'remember_me',
                'value' => $token,
                'expire' => 1209600, // 2 weeks
            ));
        }

        return $a->token;
    }
}

/* End of file user.php */
/* Location: ./application/models/user.php */
