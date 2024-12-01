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

    public array $has_many = ['content' => ['class' => 'content', 'other_field' => 'created_by'], 'application', 'history'];

    public array $validation = ['internal_id' => ['label' => 'Internal id', 'rules' => ['internalize', 'required']], 'password' => ['label' => 'password', 'rules' => ['hash_password', 'required']], 'public_first_name' => ['rules' => ['fallback']], 'public_last_name' => ['rules' => ['fallback']], 'public_email' => ['rules' => ['fallback']]];

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
        $backups = ['public_first_name', 'public_last_name', 'public_email'];
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

    public function to_array($options = [])
    {
        $this->externals = !empty($this->externals) ? unserialize($this->externals) : null;
        if (!$this->externals) {
            $this->externals = null;
        }
        $exclude = ['password', 'news', 'help', 'focal_point', 'theme'];
        $bools = [];
        [$data, $public_fields] = $this->prepare_for_output($options, $exclude, $bools);
        return $data;
    }

    public function create_session($session, $remember = false)
    {
        $a = new Application();
        $a->user_id = $this->id;
        $a->token = koken_rand();
        $a->role = 'god';
        $a->save();

        $session->set_userdata(['token' => $a->token, 'user' => $this->to_array()]);

        if ($remember) {
            $token = koken_rand();
            $this->remember_me = $token;
            $this->save();

            $this->load->helper('cookie');

            set_cookie(['name' => 'remember_me', 'value' => $token, 'expire' => 1209600]);
        }

        return $a->token;
    }
}

/* End of file user.php */
/* Location: ./application/models/user.php */
