<?php

class Histories extends Koken_Controller {

	function __construct()
    {
    	parent::__construct();
		$this->messages = json_decode(str_replace('D.messages = ', '', file_get_contents(FCPATH . 'app' .
							DIRECTORY_SEPARATOR . 'js' .
							DIRECTORY_SEPARATOR . 'core' .
							DIRECTORY_SEPARATOR . 'director-messages.js')), true);
    }

	function index()
	{
		$h = new History();
		$events = $h->include_related('user')->order_by('id DESC')->get_iterated();
		foreach($events as $e)
		{
			$message = unserialize($e->message);

			if (is_string($message))
			{
				$message = $this->messages[$message];
			}
			else
			{
				$key = array_shift($message);
				$message = vsprintf($this->messages[$key], $message);
			}
			echo $message . ' by ' . $e->user_username . ' ' . time_ago($e->created_on) . '<br>';
		}
		exit;
	}
}