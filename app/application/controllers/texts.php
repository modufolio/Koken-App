<?php

class Texts extends Koken_Controller {

	function __construct()
	{
		 parent::__construct();
	}

	function oembed_preview() {
		if (isset($_POST['url']))
		{
			$data = Shutter::get_oembed(urldecode($_POST['url']));
			$this->set_response_data($data);
		}
	}

	function drafts()
	{
		list($params,) = $this->parse_params(func_get_args());
		$params['auth'] = $this->auth;

		if (!$this->auth)
		{
			$this->error('403', 'Forbidden');
			return;
		}

		$params['state'] = 'draft';
		$params['type'] = 'essay';

		if (!isset($params['order_by']) || $params['order_by'] === 'published_on')
		{
			$params['order_by'] = 'modified_on';
		}

		$t = new Text;
		$final = $t->listing($params);
		$this->set_response_data($final);
	}

	function index()
	{
		list($params, $id, $slug) = $this->parse_params(func_get_args());
		$params['auth'] = $this->auth;

		// Create or update
		if ($this->method != 'get')
		{
			$t = new Text();
			switch($this->method)
			{
				case 'post':
				case 'put':
					if ($id)
					{
						$t->get_by_id($id);

						$t->old_published = $t->published;
						$t->current_slug = $t->slug;

						if (isset($_POST['unpublish']))
						{
							$_POST['published'] = 0;
							$_POST['published_on'] = null;
						}
					}
					else
					{
						if (isset($_POST['page_type']) && $_POST['page_type'] === 'page')
						{
							$_POST['published'] = 1;
						}
					}

					$arr = $_POST;

					global $raw_input_data;
					if (isset($raw_input_data['content']))
					{
						$arr['content'] = $raw_input_data['content'];
					}

					if (isset($raw_input_data['draft']))
					{
						$arr['draft'] = $raw_input_data['draft'];
					}

					// Little hack here to make sure content validation is always run
					// (newline gets stripped in text->_format_content)
					if (isset($arr['content']))
					{
						$arr['content'] .= "\n";
					}

					try {
						$t->from_array($arr, array(), true);
					} catch (Exception $e) {
						$this->error('400', $e->getMessage());
						return;
					}

					if (isset($_POST['tags']))
					{
						$t->_format_tags($_POST['tags']);
					}
					else if ($this->method === 'put' && isset($_POST['published']))
					{
						$t->_update_tag_counts();
					}

					$arr = $t->to_array(array('expand' => true));

					if ($id)
					{
						Shutter::hook('text.update', $arr);
					}
					else
					{
						Shutter::hook('text.create', $arr);
					}

					$this->redirect("/text/{$t->id}" . ( isset($params['render']) ? '/render:' . $params['render'] : '' ));
					break;
				case 'delete':
					if (is_null($id))
					{
						$this->error('403', 'Required parameter "id" not present.');
						return;
					}
					else
					{
						if (is_numeric($id))
						{
							$id = array($id);
						}
						else
						{
							$id = explode(',', $id);
						}

						$tags = array();

						foreach($id as $text_id)
						{
							$text = $t->get_by_id($text_id);
							if ($text->exists())
							{
								$tags = array_merge($tags, $text->tags);

								$s = new Slug;
								$prefix = $text->page_type == 0 ? 'essay' : 'page';
								$this->db->query("DELETE FROM {$s->table} WHERE id = '$prefix.{$text->slug}'");

								Shutter::hook('text.delete', $text->to_array(array('auth' => true)));

								if (!$text->delete())
								{
									// TODO: More info
									$this->error('500', 'Delete failed.');
									return;
								}
							}
						}
					}
					exit;
					break;
			}
		}
		$p = new Text();
		// No id, so we want a list
		if (is_null($id) && !$slug)
		{
			$params['state'] = 'published';
			$final = $p->listing($params);
		}
		// Get entry by id
		else
		{
			if (!is_null($id))
			{
				if (is_numeric($id))
				{
					$page = $p->get_by_id($id);
				}
				else
				{
					$this->auth = $params['auth'] = true;
					$page = $p->get_by_internal_id($id);
				}
			}
			else if ($slug)
			{
				$p->group_start()
					->where('slug', $slug)
					->or_like('old_slug', ',' . $slug . ',', 'both')
				  ->group_end();

				if (isset($params['type']))
				{
					$p->where('page_type', $params['type'] === 'essay' ? 0 : 1);
				}
				$page = $p->get();
			}

			$params['expand'] = true;

			if ($page->exists())
			{
				$final = $page->to_array($params);
				if (!$this->auth && !$final['published'])
				{
					$this->error('404', 'Not found');
					return;
				}
			}
			else
			{
				$this->error('404', "Text with ID: $id not found.");
				return;
			}

			if ($final['page_type'] === 'essay' && $page->published)
			{

				$options = array(
					'neighbors' => false,
					'context' => false,
				);
				$options = array_merge($options, $params);

				if ($options['neighbors'])
				{
					// Make sure $neighbors is at least 2
					$options['neighbors'] = max($options['neighbors'], 2);

					// Make sure neighbors is even
					if ($options['neighbors'] & 1 != 0)
					{
						$options['neighbors']++;
					}

					$options['neighbors'] = $options['neighbors']/2;
				}
				else
				{
					$options['neighbors'] = 1;
				}

				if ($options['neighbors'])
				{
					// TODO: Performance check
					$next = new Text;
					$prev = new Text;

					$to_arr_options = array('auth' => $this->auth);

					$next
						->group_start()
							->where('page_type', 0)
							->where('published', 1)
							->group_start()
								->where('published_on <', $page->published_on)
								->or_group_start()
									->where('published_on =', $page->published_on)
									->where('id <', $page->id)
								->group_end()
							->group_end()
						->group_end();

					$prev
						->group_start()
							->where('page_type', 0)
							->where('published', 1)
							->group_start()
								->where('published_on >', $page->published_on)
								->or_group_start()
									->where('published_on =', $page->published_on)
									->where('id >', $page->id)
								->group_end()
							->group_end()
						->group_end();

					if (strpos($options['context'], 'tag-') === 0)
					{
						$tag = str_replace('tag-', '', urldecode($options['context']));
						$t = new Tag;
						$t->where('name', $tag)->get();
						$to_arr_options['context'] = "tag-$tag";

						if ($t->exists())
						{
							$next->where_related_tag('id', $t->id);
							$prev->where_related_tag('id', $t->id);
							$final['context']['type'] = 'tag';
							$final['context']['title'] = $tag;
							$final['context']['slug'] = $tag;

							$t->model = 'tag_essays';
							$t->slug = $t->name;
							$url = $t->url();

							if ($url)
							{
								list($final['context']['__koken_url'], $final['context']['url']) = $url;
							}
						}
					}
					else if (strpos($options['context'], 'category-') === 0)
					{
						$category = str_replace('category-', '', $options['context']);
						$cat = new Category;
						$cat->where('slug', $category)->get();
						if ($cat->exists())
						{
							$next->where_related_category('id', $cat->id);
							$prev->where_related_category('id', $cat->id);
							$final['context']['type'] = 'category';
							$final['context']['title'] = $cat->title;
							$final['context']['slug'] = $cat->slug;

							$to_arr_options['context'] = "category-{$cat->id}";

							$cat->model = 'category_essays';
							$url = $cat->url();

							if ($url)
							{
								list($final['context']['__koken_url'], $final['context']['url']) = $url;
							}
						}
					}

					$max = $next->get_clone()->count();
					$min = $prev->get_clone()->count();

					$final['context']['total'] = $max + $min + 1;
					$final['context']['position'] = $min + 1;
					$pre_limit = $next_limit = $options['neighbors'];

					if ($min < $pre_limit)
					{
						$next_limit += ($pre_limit - $min);
						$pre_limit = $min;
					}
					if ($max < $next_limit)
					{
						$pre_limit = min($min, $pre_limit + ($next_limit - $max));
						$next_limit = $max;
					}

					$final['context']['previous'] = array();
					$final['context']['next'] = array();

					if ($next_limit > 0)
					{
						$next->order_by('published_on DESC, id DESC')
							->limit($next_limit);

						$next->get_iterated();

						foreach($next as $c)
						{
							$final['context']['next'][] = $c->to_array( $to_arr_options );
						}
					}

					if ($pre_limit > 0)
					{
						$prev->order_by('published_on ASC, id ASC')
							->limit($pre_limit);

						$prev->get_iterated();

						foreach($prev as $c)
						{
							$final['context']['previous'][] = $c->to_array( $to_arr_options );
						}
						$final['context']['previous'] = array_reverse($final['context']['previous']);
					}

				}
			}

		}
		$this->set_response_data($final);
	}

	function feature()
	{
		list(, $id) = $this->parse_params(func_get_args());

		if (is_array($id))
		{
			list($text_id, $content_id) = $id;
		}
		else
		{
			$text_id = $id;
		}

		if ($this->method === 'get')
		{
			// This is onlt for POST/DELETE operations, redirect them back to main /text GET
			$this->redirect("/text/{$text_id}");
		}
		else
		{
			$text = new Text();
			$t = $text->get_by_id($text_id);

			if (isset($_POST['file']))
			{
				if (strpos($_POST['file'], 'http') === 0)
				{
					if ($text->custom_featured_image)
					{
						delete_files(FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . str_replace('.', '-', $text->custom_featured_image), true, 1);
					}
					$info = pathinfo($_POST['file']);
					$base = 'custom_oembed_' . $text_id . '.' . (isset($info['extension']) && in_array($info['extension'], array('jpeg', 'jpg', 'gif', 'png')) ? strtolower($info['extension']) : 'jpg');
					$this->_download($_POST['file'], FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . $base);
					$_POST['file'] = $base;
				}
				$text->featured_image_id = null;
				$text->custom_featured_image = $_POST['file'];
				$text->save();
			}
			else
			{
				$content = new Content();
				$content->get_by_id($content_id);

				if ($text->custom_featured_image)
				{
					delete_files(FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . str_replace('.', '-', $text->custom_featured_image), true, 1);
				}

				$text->custom_featured_image = null;
				$text->save();

				if ($content->exists())
				{
					if ($this->method === 'post')
					{
						$t->save_featured_image($content);
					}
					else
					{
						$t->delete_featured_image($content);
					}
				}
			}

			$this->redirect("/text/{$text_id}");
			exit;
		}
	}

	function categories()
    {
		list($params, $id) = $this->parse_params(func_get_args());
		$c = new Category;

		$params['auth'] = $this->auth;
		$params['limit_to'] = 'essays';
    	$final = $c->where_related('text', 'id', $id)->listing($params);
		$this->set_response_data($final);
    }

	function topics()
	{

		list($params, $id) = $this->parse_params(func_get_args());

		if ($this->method === 'get')
		{
			$a = new Album;

			$params['auth'] = $this->auth;
			$params['flat'] = true;

	    	$final = $a->where_related('text', 'id', $id)->listing($params);
			$this->set_response_data($final);
		}
		else
		{
			list($text_id, $album_id) = $id;

			$text = new Text();
			$t = $text->get_by_id($text_id);

			if (is_numeric($album_id))
			{
				$album_id = array( $album_id );
			}
			else
			{
				$album_id = explode(',', $album_id);
			}

			$album = new Album();
			$albums = $album->where_in('id', $album_id)->get_iterated();

			foreach($albums as $a)
			{
				if ($this->method === 'post')
				{
					$a->save($t);
				}
				else
				{
					$a->delete($t);
				}
			}

			$this->redirect("/text/{$text_id}");
			exit;
		}
	}

}

/* End of file albums.php */
/* Location: ./system/application/controllers/pages.php */