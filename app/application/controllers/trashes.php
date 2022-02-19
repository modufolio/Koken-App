<?php

class Trashes extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

	function index()
	{
		// TODO: Make sure user is admin over content they trash
		list($params, $id) = $this->parse_params(func_get_args());

		if ($this->method != 'get')
		{
			$c = new Content();
			$a = new Album();
			$t = new Trash();
			$tag = new Tag();

			$options = array(
				'content' => array(),
				'albums' => array()
			);

			$params = array_merge($options, $params);

			if (!empty($params['content']))
			{
				$params['content'] = explode(',', $params['content']);
			}

			if (!empty($params['albums']))
			{
				$params['albums'] = explode(',', $params['albums']);
			}

			switch($this->method)
			{
				case 'post':
					$q = array();
					$content_ids = array();
					$album_ids = array();

					$now = time();

					if (!empty($params['content']))
					{
						$content = $c->where_in('id', $params['content'])->get_iterated();
						foreach($content as $c)
						{
							$s = serialize($c->to_array( array('auth' => $this->auth)));
							$q[] = "('content-{$c->id}', '" . $this->db->escape_str(MB_ENABLED ? mb_convert_encoding($s, 'UTF-8') : utf8_encode($s)) . "', $now)";
						}
					}

					if (!empty($params['albums']))
					{
						foreach($params['albums'] as $album_id)
						{
							$al = new Album;
							$al->get_by_id($album_id);

							if ($al->exists())
							{
								$s = serialize($al->to_array());
								$q[] = "('album-{$al->id}', '" . $this->db->escape_str(MB_ENABLED ? mb_convert_encoding($s, 'UTF-8') : utf8_encode($s)) . "', $now)";
								$al->tree_trash();

								foreach($al->categories->get_iterated() as $category)
								{
									$category->update_counts('album');
								}

								foreach($al->tags->get_iterated() as $tag)
								{
									$tag->update_counts('album');
								}
							}
						}

						$a->update_set_counts();
					}

					if (!empty($q))
					{
						$q = join(',', $q);
						$this->db->query("INSERT INTO {$t->table} VALUES $q ON DUPLICATE KEY UPDATE data = VALUES(data)");
					}

					if (!empty($params['content']))
					{
						$c->where_in('id', $params['content'])->update('deleted', 1);

						$albums = $a->where_in_related('content', 'id', $params['content'])->get_iterated();
						foreach($albums as $a)
						{
							$a->update_counts();
						}

						$previews = $a->where_in_related('cover', 'id', $params['content'])->distinct()->get_iterated();

						$prefix = preg_replace('/trash$/', '', $t->table);
						$this->db->query("DELETE FROM {$prefix}join_albums_covers WHERE cover_id IN(" . join(',', $params['content']) . ")");

						foreach($previews as $a)
						{
							$a->reset_covers();
						}

						foreach($c->where_in('id', $params['content'])->get_iterated() as $content)
						{
							foreach($content->categories->get_iterated() as $category)
							{
								$category->update_counts('content');
							}

							foreach($content->tags->get_iterated() as $tag)
							{
								$tag->update_counts('content');
							}
						}
					}
					$this->redirect('/trash');
					break;
				case 'delete':
					$ids = array();
					foreach($params['content'] as $id)
					{
						$ids[] = "'content-{$id}'";
					}

					foreach($params['albums'] as $id)
					{
						$ids[] = "'album-{$id}'";
					}

					if (!empty($ids))
					{
						$ids = join(',', $ids);
						$this->db->query("DELETE FROM {$t->table} WHERE id IN ($ids)");
					}

					if (!empty($params['albums']))
					{
						foreach($params['albums'] as $album_id)
						{
							$al = new Album;
							$al->get_by_id($album_id);

							if ($al->exists())
							{
								$al->tree_trash_restore();

								foreach($al->categories->get_iterated() as $category)
								{
									$category->update_counts('album');
								}

								foreach($al->tags->get_iterated() as $tag)
								{
									$tag->update_counts('album');
								}
							}
						}

						$a->update_set_counts();
					}

					if (!empty($params['content']))
					{
						$c->where_in('id', $params['content'])->update('deleted', 0);

						$covers = $a->where_in_related('cover', 'id', $params['content'])->distinct()->get_iterated();

						foreach($covers as $a)
						{
							$a->reset_covers();
						}

						$albums = $a->where_in_related('content', 'id', $params['content'])->get_iterated();
						foreach($albums as $a)
						{
							$a->update_counts();
						}

						foreach($c->where_in('id', $params['content'])->get_iterated() as $content)
						{
							foreach($content->categories->get_iterated() as $category)
							{
								$category->update_counts('content');
							}

							foreach($content->tags->get_iterated() as $tag)
							{
								$tag->update_counts('content');
							}
						}
					}
					exit;
					break;
			}
		}

		$options = array(
			'page' => 1,
			'limit' => 100
		);
		$options = array_merge($options, $params);
		if (is_numeric($options['limit']) && $options['limit'] > 0)
		{
			$options['limit'] = min($options['limit'], 100);
		}
		else
		{
			$options['limit'] = 100;
		}

		$t = new Trash();
		$final = $t->paginate($options);
		$data = $t->order_by('created_on DESC')->get_iterated();

		$final['trash'] = array();
		foreach($data as $member)
		{
			$content = unserialize(MB_ENABLED ? mb_convert_encoding($member->data, 'ISO-8859-1') : utf8_decode($member->data));
			if (!$content)
			{
				$content = unserialize($member->data);
			}
			if (isset($content['description']))
			{
				$type = 'album';
			}
			else
			{
				$type = 'content';
			}
			if ($content)
			{
				$final['trash'][] = array('type' => $type, 'data' => $content);
			}
			else
			{
				$final['total']--;
			}
		}
		$this->set_response_data($final);
	}

}

/* End of file trashes.php */
/* Location: ./system/application/controllers/trashes.php */