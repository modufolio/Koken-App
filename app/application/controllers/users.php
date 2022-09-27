<?php

class Users extends Koken_Controller
{
    public function __construct()
    {
        $this->auto_authenticate = array('exclude' => array('reset_password'));
        parent::__construct();
    }

    public function reset_password($id = false)
    {
        $koken_url_info = $this->config->item('koken_url_info');
        $this->load->library('email');

        if (isset($_POST['email']) && !empty($_POST['email'])) {
            $user = $_POST['email'];
            $u = new User();
            $u->where('email', $user)->get();
            if ($u->exists()) {
                $subject = 'Koken: Password reset requested';
                $message = "Hi there -\n\nSomeone (hopefully you!) just requested that the password to your Koken installation at $koken_url_info->base be reset. If you did not request a password reset, ignore this email and your password will stay the same. If you do need your password reset, click the link below.\n\n{$koken_url_info->base}api.php?/users/reset_password/$u->internal_id\n\n- Koken";
                Shutter::email($u->email, 'Koken', $u->email, $subject, $message);
                $this->set_response_data(array('success' => true));
            } else {
                $this->error('404', 'User not found.');
                return;
            }
        } elseif ($id) {
            $u = new User();
            $u->where('internal_id', $id)->get();
            if ($u->exists()) {
                $new = substr(koken_rand(), 0, 8);
                $u->password = $new;
                $u->save();

                $subject = 'Koken: Your password has been reset';
                $message = "Your Koken password has been successfully reset.\n\nYour new password: $new\n\n- Koken";
                Shutter::email($u->email, 'Koken', $u->email, $subject, $message);

                echo $message;
                exit;
            } else {
                $this->error('404', 'User not found.');
                return;
            }
        } else {
            $this->error('400', 'Bad request');
            return;
        }
    }

    public function verify_password()
    {
        if ($this->method === 'post') {
            $u = new User();
            $u->get_by_id($this->auth_user_id);

            if ($u->exists()) {
                if ($u->check_password($_POST['password'])) {
                    exit;
                } else {
                    $this->error('403', 'Password does not match');
                    return;
                }
            } else {
                $this->error('404', 'User not found.');
                return;
            }
        } else {
            $this->error('400', 'Bad request');
            return;
        }
    }

    public function index()
    {
       [$params, $id] = $this->parse_params(func_get_args());
        // Create or update
        if ($this->method === 'get') {
            if (!$this->auth) {
                $this->error('401', 'Not authorized to perform this action.');
                return;
            }
        } else {
            // TODO: Stress test permissions
            $u = new User();
            switch ($this->method) {
                case 'post':
                case 'put':
                    if ($this->method == 'put') {
                        // Updates can only be carried out by the user or an administrator
                        if ($this->auth_user_id != $id &&
                            $this->auth_role != 'god' && $this->auth_role != 'admin') {
                            $this->error('401', 'Not authorized to perform this action.');
                            return;
                        }
                        $u->get_by_id($id);
                        if (!$u->exists()) {
                            $this->error('404', "User with ID: $id not found.");
                            return;
                        }
                    } elseif (is_null($id)) {
                        // Only admins can create users
                        if ($this->auth_role != 'god' && $this->auth_role != 'admin') {
                            $this->error('401', 'Not authorized to perform this action.');
                            return;
                        }
                    }
                    $u->from_array($_POST, array(), true);
                    $this->redirect("/users/$u->id");
                    break;
                // case 'delete':
                // 	if ($this->auth_role != 'god' && $this->auth_role != 'admin')
                // 	{
                // 		$this->error('401', 'Not authorized to perform this action.');
                    return;
                // 	}
                // 	if (is_null($id))
                // 	{
                // 		$this->error('403', 'Required parameter "id" not present.');
                    return;
                // 	}
                // 	else
                // 	{
                // 		// TODO
                // 	}
                // 	exit;
                    break;
            }
        }
        $u = new User();
        // No id, so we want a list
        if (is_null($id)) {
            $options = array(
                'page' => 1,
                'limit' => false
            );
            $options = array_merge($options, $params);
            if (!is_numeric($options['limit'])) {
                $options['limit'] = false;
            }

            $final = $u->paginate($options);
            $data = $u->get_iterated();

            if (!$options['limit']) {
                $final['per_page'] = $data->result_count();
                $final['total'] = $data->result_count();
            }

            $final['users'] = [];
            foreach ($data as $user) {
                $final['users'][] = $user->to_array($params);
            }
        }
        // Get user by id
        else {
            $user = $u->get_by_id($id);
            if ($u->exists()) {
                $final = $user->to_array($params);
            } else {
                $this->error('404', "User with ID: $id not found.");
                return;
            }
        }
        $this->set_response_data($final);
    }

    public function content()
    {
       [$params, $id] = $this->parse_params(func_get_args());
        $c = new Content();

        if (is_null($id)) {
            $this->error('403', 'Required parameter "id" not present.');
            return;
        }

        $options = array(
            'order_by' => 'created_on',
            'order_direction' => 'DESC',
            'images_only' => false,
            'videos_only' => false,
            'audio_only' => false,
            'page' => 1,
            'limit' => false
        );
        $options = array_merge($options, $params);
        if (!is_numeric($options['limit'])) {
            $options['limit'] = false;
        }

        if ($options['images_only']) {
            $c->where('file_type', 0);
        } elseif ($options['videos_only']) {
            $c->where('file_type', 1);
        } elseif ($options['audio_only']) {
            $c->where('file_type', 2);
        }
        $c->where('created_by', $id);
        $final = $c->paginate($options);
        $c->order_by($options['order_by'] . ' ' . $options['order_direction']);

        $data = $c->get_iterated();
        if (!isset($final['per_page'])) {
            $final['per_page'] = $data->result_count();
            $final['total'] = $data->result_count();
        }
        $final['content'] = [];
        foreach ($data as $content) {
            $final['content'][] = $content->to_array($params);
        }

        $this->set_response_data($final);
    }
}

/* End of file users.php */
/* Location: ./system/application/controllers/users.php */
