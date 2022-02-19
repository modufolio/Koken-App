<?php

class Tags extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

	function index()
	{
		$defaults = array('page' => 1, 'limit' => 20, 'context_order' => 'count');

		list($params,$id,$slug) = $this->parse_params(func_get_args());

		$params = array_merge($defaults, $params);

		$t = new Tag();

		if ($this->method !== 'get')
		{
			if (is_null($id))
			{
				$this->error('400', 'ID is required.');
			}

			$tag = $t->get_by_id($id);

			if ($this->method === 'delete')
			{
				$tag->delete();
				exit;
			}
			else
			{
				$tag->name = $this->input->post('name');
				$tag->save();

				$this->redirect('/tags/' . $tag->id);
			}
		}

		if (!$slug && is_null($id))
		{
			$final = $t->listing($params);
		}
		else
		{
			$slug = urldecode($slug);

			if ($slug) {
				$t->where('name', $slug)->get();
			}
			else
			{
				$t->get_by_id($id);
			}

			if (!$t->exists())
			{
				$final = array();
			}
			else
			{
				$tag_array = $t->to_array();

				$params['tag'] = $t->id;
				$params['tag_slug'] = $t->name;
				list($final, $counts) = $this->aggregate('tag', $params);

				$final['counts'] = $counts;
				$final = array_merge($tag_array, $final);

				$prev = new Tag;
				$next = new Tag;

				$prev->where('id !=', $t->id);
				$next->where('id !=', $t->id);

				if ($params['context_order'] === 'count')
				{
					$prev->group_start();
						$prev->where_func('', array('@content_count', '+', '@text_count',  '+',  '@album_count', '>', $t->essay_count + $t->album_count + $t->content_count), null);
						$prev->or_group_start();
							$prev->where_func('', array('@content_count', '+', '@text_count',  '+',  '@album_count', '=', $t->essay_count + $t->album_count + $t->content_count), null);
							$prev->where('name <', $t->name);
						$prev->group_end();
					$prev->group_end();

					$next->group_start();
						$next->where_func('', array('@content_count', '+', '@text_count',  '+',  '@album_count', '<', $t->essay_count + $t->album_count + $t->content_count), null);
						$next->or_group_start();
							$next->where_func('', array('@content_count', '+', '@text_count',  '+',  '@album_count', '=', $t->essay_count + $t->album_count + $t->content_count), null);
							$next->where('name >', $t->name);
						$next->group_end();
					$next->group_end();

					$prev->order_by_func('', array('@content_count', '+', '@text_count',  '+',  '@album_count'), 'ASC');
					$next->order_by_func('', array('@content_count', '+', '@text_count',  '+',  '@album_count'), 'DESC');
				}
				else
				{
					$prev->where('name <', $t->name);
					$next->where('name >', $t->name);
				}

				$prev->order_by('name DESC');
				$next->order_by('name ASC');

				$max = $next->get_clone()->count();
				$min = $prev->get_clone()->count();

				$final['context'] = array();
				$final['context']['total'] = $max + $min + 1;
				$final['context']['position'] = $min + 1;
				$final['context']['previous'] = $final['context']['next'] = false;

				$prev->get();
				$next->get();

				if ($prev->exists())
				{
					$final['context']['previous'] = $prev->to_array();
				}

				if ($next->exists())
				{
					$final['context']['next'] = $next->to_array();
				}
			}
		}

		$this->set_response_data($final);
	}

}