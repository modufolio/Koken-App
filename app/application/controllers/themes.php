<?php

class Themes extends Koken_Controller {

	function __construct()
    {
		$this->caching = false;
		parent::__construct();
    }

	function index()
	{
		list($params, $id) = $this->parse_params(func_get_args());

		$t = new Theme;
		$final = $t->read();

		$final = Shutter::filter('api.themes', array($final));

		$this->set_response_data($final);
	}
}

/* End of file themes.php */
/* Location: ./system/application/controllers/themes.php */