<?php

class Auth extends Koken_Controller {

	function __construct()
    {
    	$this->auto_authenticate = false;
        parent::__construct();
    }

	function index()
	{
		$auth = $this->authenticate();
		if (!$auth)
		{
			$this->error('401', 'Not logged in.');
			return;
		}
		if ($auth[2] != 'god')
		{
			$this->error('403', 'Applications can only be authenticated/revoked/listed via the Koken console.');
			return;
		}

		if ($this->method === 'post')
		{
			$_POST['token'] = koken_rand();
			$a = new Application;
			$a->from_array($_POST, array(), true);
			$this->redirect('/auth/token:' . $auth[1]);
		}

		if ($this->method === 'delete')
		{
			list($params, $id) = $this->parse_params(func_get_args());
			$a = new Application();
			$a->where('id', $id)->get();

			if ($a->exists())
			{
				$a->delete();
				$this->redirect('/auth/token:' . $auth[1]);
			}

		}

		$a = new Application();
		$a->where('role !=', 'god')->get_iterated();

		$apps = array();
		foreach($a as $app)
		{
			$apps[] = $app->to_array();
		}
		$this->set_response_data(array( 'applications' => $apps ) );
	}

	function grant()
	{
		$auth = $this->authenticate();
		if (!$auth)
		{
			$this->error('401', 'Not logged in.');
			return;
		}
		if ($auth[2] != 'god')
		{
			$this->error('403', 'Applications can only be authenticated via the Koken console.');
			return;
		}

		$roles = array('read', 'read-write');

		if (!in_array($_POST['role'], $roles))
		{
			$this->_error(400, "Incorrect role request. Valid values are \"read\" and \"read-write\"", 'html');
		}

		$_POST['token'] = koken_rand();

		$a = new Application();
		$a->from_array($_POST, array(), true);
		$this->redirect('/auth/token:' . $auth[1]);
		exit;
	}

	function token($nonce)
	{
		// TODO: Add time limit to nonce so it can't be called again (5 min?)
		$a = new Application();
		$application = $a->where('nonce', $nonce)->get();
		if ($application->exists())
		{
			$application->user->get();
			$data = array(
				'token' => $application->token,
				'role' => $application->role,
				'user' => $application->user->first_name . ' ' . $application->user->last_name,
				'host' => $_SERVER['HTTP_HOST'],
				'ssl' => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'),
			);
		}
		else
		{
			$this->error(404, "Token not found.");
			return;
		}
		$this->set_response_data($data);
	}

}

/* End of file trashes.php */
/* Location: ./system/application/controllers/trashes.php */