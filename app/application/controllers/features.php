<?php

class Features extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

	function index()
	{

		list($params, $id) = $this->parse_params(func_get_args());

		$model = trim($params['model'], 's');
		$c = new $model();

		if ($this->method != 'get')
		{

			if ($this->method != 'put' && is_null($id))
			{
				$this->error('403', 'Required parameter "id" not present.');
				return;
			}

			$tail = '';

			switch($this->method)
			{
				case 'put':
					if (isset($params['order']))
					{
						$ids = explode(',', $params['order']);
						$new_order_map = array();

						foreach($ids as $key => $val)
						{
							$pos = $key + 1;
							$new_order_map[$val] = $pos;
						}

						$favs = $c->where('featured', 1)->order_by('featured_order ASC')->get_iterated();
						foreach($favs as $f)
						{
							if (isset($new_order_map[$f->id]) && $new_order_map[$f->id] != $f->featured_order)
							{
								echo $new_order_map[$f->id];
								$f->where('id', $f->id)->update('featured_order', $new_order_map[$f->id]);
							}
						}
					}
					break;
				case 'post':
				case 'delete':
					if (is_numeric($id))
					{
						$id = array($id);
					}
					else
					{
						$id = explode(',', $id);
					}

					if ($this->method == 'delete')
					{
						$c->where_in('id', $id)->update( array( 'featured' => 0, 'featured_order' => null, 'featured_on' => null ) );
					}
					else
					{
						$max = $c->select_func('max', '@featured_order', 'max_featured')->where('featured', 1)->get();
						if (!is_numeric($max->max_featured))
						{
							$max_order = 1;
						}
						else
						{
							$max_order = $max->max_featured;
						}
						foreach($id as $id)
						{
							$c->where('id', $id);
							if ($model === 'text')
							{
								$c->where('page_type', 0)->where('published', 1);
							}
							$c->update( array( 'featured' => 1, 'featured_order' => $max_order++, 'featured_on' => strtotime(gmdate('Y-m-d H:i:s')) ) );
						}
					}

					break;
			}

			if ($this->method == 'delete')
			{
				exit;
			}
			else
			{
				$this->redirect("/{$params['model']}/featured/order_by:manual");
			}
		}


		$options = array(
			'order_by' => 'manual',
			'featured' => true
		);

		if ($model === 'content')
		{
			$sort = $c->_get_site_order('feature');
			$options['order_by'] = $sort['by'];
			$options['order_direction'] = $sort['direction'];
		}

		$params = array_merge($options, $params);
		if ($params['order_by'] === 'manual')
		{
			$params['order_by'] = 'featured_order, featured_on';
			$params['order_direction'] = 'asc';
		}
		$params['auth'] = $this->auth;
		if ($model !== 'text')
		{
			$c->where('deleted', 0);
		}
		$final = $c->listing($params);

		if (isset($sort))
		{
			$final['sort'] = $sort;
		}

		$this->set_response_data($final);
	}

}

/* End of file albums.php */
/* Location: ./system/application/controllers/features.php */