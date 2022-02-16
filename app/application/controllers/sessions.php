<?php

class Sessions extends Koken_Controller {

	function __construct()
    {
    	$this->caching = false;
    	$this->purges_cache = false;
		$this->auto_authenticate = false;
        parent::__construct();
    }

	function index()
	{
		// GC old sessions
		if ($this->method !== 'delete')
		{
			$gc = new Application;
			$gc->where('role', 'god')->where('created_on <', strtotime('-14 days'))->get();
			$gc->delete_all();
		}

		if ($this->method == 'get')
		{
			$auth = $this->authenticate();
			if ($auth)
			{
				$user_id = $auth[0];
				$u = new User();
				$u->get_by_id($user_id);
				if ($u->exists())
				{
					$this->set_response_data(array(
						'token' => $auth[1],
						'user' => $u->to_array()
					));
				}
				else
				{
					$this->error('404', 'User not found.');
					return;
				}
			}
			else
			{
				$this->error('404', 'Session not found.');
				return;
			}
		}
		else
		{
			switch($this->method)
			{
				case 'post':
					$u = new User;
					if ($this->input->post('email') && $this->input->post('password'))
					{
						$u->where('email', $this->input->post('email'))
							->limit(1)
							->get();

						if ($u->exists() && $u->check_password($this->input->post('password')))
						{
							$u->create_session($this->session, $this->input->post('remember') === 'on');
						}
						else
						{
							$this->error('404', 'User not found.');
							return;
						}
					}
					else
					{
						$this->error('403', 'Required parameters "email" and/or "password" are not present.');
						return;
					}
					$this->redirect("/sessions");
					break;
				case 'delete':
					$auth = $this->authenticate();
					if (!$auth)
					{
						$this->error('401', 'Not authorized to perform this action.');
						return;
					}
					$a = new Application();
					$a->where('token', $auth[1])->get();
					$a->delete();

					$user_id = $auth[0];
					$u = new User();
					$u->get_by_id($user_id);
					$u->remember_me = null;
					$u->save();

					$this->load->helper('cookie');
					delete_cookie('remember_me');

					$this->session->sess_destroy();
					exit;
					break;
			}
		}
	}
}

/* End of file sessions.php */
/* Location: ./system/application/controllers/sessions.php */