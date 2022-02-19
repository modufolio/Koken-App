<?php

class Drafts extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }
	
	function index()
	{
		list($params, $id) = $this->parse_params(func_get_args());
		
		$theme = new Theme;
		$themes = $theme->read(true);

		if ($this->method == 'post' && isset($_POST['theme']))
		{
			$t = $_POST['theme'];
			if (isset($themes[$t]))
			{
				$d = new Draft;
				$d->where('draft', 1)->update('draft', 0);

				$d->where('path', $t)->get();
				
				$d->path = $t;
				$d->draft = 1;
				if (isset($_POST['refresh']))
				{
					$d->init_draft_nav($_POST['refresh']);					
				}
				$d->save();
				$this->redirect('/drafts');
			}
			else
			{
				// error
			}
		}
		else if ($this->method == 'delete' && isset($_POST['theme']))
		{
			$d = new Draft;
			$d->where('path', $_POST['theme'])->get();

			if ($d->exists())
			{
				$d->delete();
			}
			exit;
		}

		$final = array();
		$d = new Draft;
		$drafts = $d->get_iterated();

		foreach($drafts as $d) 
		{
			if (isset($themes[$d->path]))
			{
				$final[] = array(
					'id' => $d->id,
					'theme' => $themes[$d->path],
					'published' => (bool) $d->current,
					'active' => (bool) $d->draft,
					'created_on' => (int) $d->created_on,
					'modified_on' => (int) $d->modified_on
				);
			}
		}
		$this->set_response_data($final);
	}
}

/* End of file themes.php */
/* Location: ./system/application/controllers/themes.php */