<?php

class Favorites extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

	function index()
	{
		// TODO: Make sure user is admin over content they fave
		list($params, $id) = $this->parse_params(func_get_args());

		if ($this->method != 'get')
		{
			$c = new Content();
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

						$favs = $c->where('favorite', 1)->order_by('favorite_order ASC')->get_iterated();
						foreach($favs as $f)
						{
							if (isset($new_order_map[$f->id]) && $new_order_map[$f->id] != $f->favorite_order)
							{
								echo $new_order_map[$f->id];
								$f->where('id', $f->id)->update('favorite_order', $new_order_map[$f->id]);
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
						$c->where_in('id', $id)->update( array( 'favorite' => 0, 'favorite_order' => null, 'favorited_on' => null ) );
					}
					else
					{
						$max = $c->select_func('max', '@favorite_order', 'max_favorite')->where('favorite', 1)->get();
						if (!is_numeric($max->max_favorite))
						{
							$max_order = 1;
						}
						else
						{
							$max_order = $max->max_favorite;
						}
						foreach($id as $id)
						{
							$c->where('id', $id)->update( array( 'favorite' => 1, 'favorite_order' => $max_order++, 'favorited_on' => strtotime(gmdate('Y-m-d H:i:s')) ) );
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
				$this->redirect('/favorites');
			}
		}

		$c2 = new Content();
		$c2->where('favorite', 1)->where('deleted', 0);

		$sort = $c2->_get_site_order('favorite');

		$options = array(
			'order_by' => $sort['by'],
			'order_direction' => $sort['direction'],
			'favorite' => true
		);

		$params = array_merge($options, $params);
		if ($params['order_by'] === 'manual')
		{
			$params['order_by'] = 'favorite_order, favorited_on';
			$params['order_direction'] = 'asc';
		}
		$params['auth'] = $this->auth;
		$final = $c2->listing($params);

		$final['sort'] = $sort;

		$this->set_response_data($final);
	}

}

/* End of file albums.php */
/* Location: ./system/application/controllers/favorites.php */