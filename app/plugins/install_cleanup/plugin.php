<?php

class DDI_AfterInstall extends KokenPlugin {

	function __construct()
	{
		$this->register_hook('install.complete', 'after_complete');
	}

	function after_complete()
	{
		if (defined('ENVIRONMENT') && ENVIRONMENT === 'production')
		{
			unlink(FCPATH . 'app/application/controllers/install.php');
		}
	}
}