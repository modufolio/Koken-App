<?php

class Album extends Koken {

	var $validation = array(
		'internal_id' => array(
			'label' => 'Internal id',
			'rules' => array('internalize', 'required')
		),
		'created_on' => array(
			'rules' => array('validate_time')
		),
		'published_on' => array(
			'rules' => array('validate_time')
		),
		'left_id' => array(
			'rules' => array('into_tree', 'required')
		),
		'visibility' => array(
			'rules' => array('tree')
		),
		'title' => array(
			'rules' => array('required'),
			'get_rules' => array('readify')
		),
		'slug' => array(
			'rules' => array('slug', 'required')
		)
 	);

	var $db_join_prefix;

	function repair_tree()
	{
		$query = <<<Q
SELECT	*
FROM		{$this->table} mto
INNER		JOIN (
					SELECT left_id,visibility, deleted FROM {$this->table}
					GROUP BY left_id, visibility, deleted HAVING count(id) > 1
					LIMIT 1
				) mti
				ON 	mto.left_id = mti.left_id
				AND mto.visibility = mti.visibility
				AND mto.deleted = mti.deleted
ORDER BY mto.left_id ASC, album_type ASC, id ASC
LIMIT 1
Q;

		$fresh = new Album;
		$dupes = $fresh->query($query);

		if (count($dupes->all) < 1)
		{
			return true;
		}

		$keep = $dupes->all[0];

		$this->where('visibility', $keep->visibility)
			 ->where('deleted', $keep->deleted)
			 ->where('right_id >=', $keep->right_id)
			 ->where('id !=', $keep->id)
			 ->update(array(
				'right_id' => "right_id + 1",
			 ), false);

		$this->where('visibility', $keep->visibility)
			 ->where('deleted', $keep->deleted)
			 ->where('left_id >=', $keep->left_id)
			 ->where('id !=', $keep->id)
			 ->update(array(
				'left_id' => "left_id + 1",
			 ), false);

		return $this->repair_tree();
	}

	function _into_tree()
	{
		if (is_null($this->left_id))
		{
			if (!is_numeric($this->visibility))
			{
				$values = array('public', 'unlisted', 'private');

				if (in_array($this->visibility, $values))
				{
					$visibility = array_search($this->visibility, $values);
				}
				else
				{
					$visibility = 0;
				}
			}
			else
			{
				$visibility = $this->visibility;
			}

			$check = new Album();
			$r = $check->where('visibility', $visibility)->select_max('right_id')->get()->right_id;

			if (!is_numeric($r))
			{
				$r = 0;
			}
			$this->left_id = $r + 1;
			$this->right_id = $r + 2;
		}
	}

	function _slug($field)
	{
		if ($this->edit_slug())
		{
			return true;
		}

		if (!empty($this->slug) && $this->slug !== '__generate__')
		{
			return true;
		}

		$this->load->helper(array('url', 'text', 'string'));
		$slug = reduce_multiples(
					strtolower(
						url_title(
							convert_accented_characters($this->title), 'dash'
						)
					)
				, '-', true);

		if (empty($slug))
		{
			$t = new Album;
			$max = $t->select_max('id')->get();
			$slug = $max->id + 1;
		}

		if (is_numeric($slug))
		{
			$slug = "$slug-1";
		}

		$s = new Slug;

		while($s->where('id', "album.$slug")->count() > 0)
		{
			$slug = increment_string($slug, '-');
		}

		$this->db->query("INSERT INTO {$s->table}(id) VALUES ('album.$slug')");

		$this->slug = $slug;
	}

	function update_set_counts()
	{
		$a = new Album();
		$a->where('album_type', 2)
			->update(array(
				'total_count' => "(right_id - left_id - 1)/2"
			), false);
	}

	function update_counts($save = true, $special_case = false)
	{
		if (is_null($this->visibility)) return;

		$c = $this->content->where('deleted', 0);

		if ($special_case)
		{
			$values = array('public', 'unlisted', 'private');
			$visibility_translated = array_search($special_case['visibility'], $values);

			if ($visibility_translated <= $this->visibility)
			{
				$c->group_start()
					->where('visibility <=', $this->visibility)
					->or_where('id', $special_case['id'])
				->group_end();
			}
			else
			{
				$c->where('visibility <=', $this->visibility);
				$c->where('id !=', $special_case['id']);
			}
		}
		else
		{
			$c->where('visibility <=', $this->visibility);
		}

		$this->total_count = $c->get_clone()->count();
		$this->video_count = $c->get_clone()->where('file_type', 1)->count();

		foreach($this->categories->get_iterated() as $category)
		{
			$category->update_counts('album');
		}

		foreach($this->tags->get_iterated() as $tag)
		{
			$tag->update_counts('album');
		}

		if ($save)
		{
			$this->save();
		}
	}

	function tree_trash_restore()
	{
		$check = new Album();
		$max_right = $check->where('visibility', $this->visibility)->where('deleted', 0)->select_max('right_id')->get()->right_id;

		if (is_numeric($max_right))
		{
			$max_right++;
		}
		else
		{
			$max_right = 1;
		}

		$diff = $this->left_id - $max_right;
		$level_diff = $this->level - 1;

		if ($diff === 0)
		{
			$this->where('visibility', $this->visibility)->where('deleted', 1)->where('left_id >=', $this->left_id)->where('right_id <=', $this->right_id)->update(array(
					'level' => "level - $level_diff",
					'deleted' => 0
				), false);
		}
		else
		{
			if ($diff < 0)
			{
				$op = '+';
			}
			else
			{
				$op = '-';
			}
			$diff = abs($diff);
			$this->where('visibility', $this->visibility)->where('deleted', 1)->where('left_id >=', $this->left_id)->where('right_id <=', $this->right_id)->update(array(
					'right_id' => "right_id $op $diff",
					'left_id' => "left_id $op $diff",
					'level' => "level - $level_diff",
					'deleted' => 0
				), false);
		}
	}

	function tree_trash()
	{
		if ($this->deleted < 1)
		{
			$size = ($this->right_id - $this->left_id) + 1;

			$check = new Album();
			$max_right = $check->where('deleted', 1)->select_max('right_id')->get()->right_id;

			if (is_numeric($max_right))
			{
				$max_right++;
			}
			else
			{
				$max_right = 1;
			}

			$diff = $this->left_id - $max_right;
			$level_diff = $this->level - 1;

			$update = array('deleted' => 1, 'level' => "level - $level_diff");

			if ($diff !== 0)
			{
				if ($diff < 0)
				{
					$op = '+';
				}
				else
				{
					$op = '-';
				}
				$diff = abs($diff);
				$update['right_id'] = "right_id $op $diff";
				$update['left_id'] = "left_id $op $diff";
			}

			$this->where('visibility', $this->visibility)
					->where('deleted', 0)
					->where('left_id >=', $this->left_id)
					->where('right_id <=', $this->right_id)
					->update($update, false);


			$this->where('visibility', $this->visibility)->where('deleted', 0)->where('right_id >', $this->right_id)->update(array(
					'right_id' => "right_id - $size",
				), false);

			$this->where('visibility', $this->visibility)->where('deleted', 0)->where('left_id >', $this->right_id)->update(array(
					'left_id' => "left_id - $size",
				), false);

		}
	}

	function _do_match_visibility($params = false)
	{
		if (!$params)
		{
			$params = array(
				'left_id' => $this->left_id,
				'right_id' => $this->right_id,
				'visibility' => $this->visibility,
			);
		}

		$aggregator = new Album;
		$aggregator->select('id')->where('left_id >=', $params['left_id'])->where('right_id <=', $params['right_id'])->get_iterated();

		$ids = array();
		foreach($aggregator as $album)
		{
			$ids[] = $album->id;
		}

		$c = new Content;
		$c->select('id')->where_in_related('album', 'id', $ids)->get_iterated();

		$cids = array();
		foreach($c as $content)
		{
			$cids[] = $content->id;
		}

		if (count($cids))
		{
			$c = new Content;
			$c->where_in('id', $cids);

			if ($this->old_visibility < 2)
			{
				$c->where('visibility <', 2);
			}

			$c->update(array('visibility' => $params['visibility']));
		}
	}

	function make_listed()
	{
		if ($this->id && isset($_POST['match_album_visibility']) && $_POST['match_album_visibility'] > 0)
		{
			$this->_do_match_visibility();
		}

		$old_right = $this->right_id;
		$size = ($this->right_id - $this->left_id) + 1;

		$check = new Album();
		$max_right = $check->where('visibility', $this->visibility)->select_max('right_id')->get()->right_id;

		if (is_numeric($max_right))
		{
			$max_right++;
		}
		else
		{
			$max_right = 1;
		}

		$diff = $this->left_id - $max_right;
		$level_diff = $this->level - 1;

		if (is_numeric($this->id))
		{
			if ($diff === 0)
			{
				$this->where('visibility', $this->old_visibility)->where('left_id >=', $this->left_id)->where('right_id <=', $this->right_id)->update(array(
						'visibility' => $this->visibility,
						'level' => "level - $level_diff"
					), false);
			}
			else
			{
				if ($diff < 0)
				{
					$op = '+';
				}
				else
				{
					$op = '-';
				}
				$diff = abs($diff);
				$this->where('visibility', $this->old_visibility)->where('left_id >=', $this->left_id)->where('right_id <=', $this->right_id)->update(array(
						'right_id' => "right_id $op $diff",
						'left_id' => "left_id $op $diff",
						'visibility' => $this->visibility,
						'level' => "level - $level_diff"
					), false);
			}

			$this->where('visibility', $this->old_visibility)->where('right_id >', $old_right)->update(array(
					'right_id' => "right_id - $size",
				), false);

			$this->where('visibility', $this->old_visibility)->where('left_id >', $old_right)->update(array(
					'left_id' => "left_id - $size",
				), false);

			if ($this->album_type < 1)
			{
				$this->update_counts();
			}
		}

		$this->update_set_counts();
	}

	function _tree($field)
	{
		$values = array('public', 'unlisted', 'private');

		if (in_array($this->visibility, $values))
		{
			$this->visibility = array_search($this->visibility, $values);
		}
		else
		{
			return false;
		}

		if ($this->visibility < 1)
		{
			$this->published_on = null;
		}

		$this->make_listed();
	}

	/**
	 * Create internal ID if one is not present
	 */
	function _internalize($field)
	{
		$this->{$field} = koken_rand();
	}

	/**
	 * Constructor: calls parent constructor
	 */
    function __construct($id = NULL)
	{
		$db_config = Shutter::get_db_configuration();

		$this->db_join_prefix = $db_config['prefix'] . 'join_';

		$this->has_many = array(
			'content',
			'category',
			'text',
			'tag',
			'cover' => array(
				'class' => 'content',
				'join_table' => $this->db_join_prefix . 'albums_covers',
				'other_field' => 'covers',
				'join_other_as' => 'cover',
				'join_self_as' => 'album',
			)
		);

		parent::__construct($id);
    }

	function context($params, $auth)
	{
		if (!$params['neighbors'])
		{
			$single_neighbors = true;
			$n = 1;
		}
		else
		{
			$single_neighbors = false;
			$n = $params['neighbors']/2;
		}

		$to_arr_options = array('auth' => $auth);

		if (!isset($params['context_order']))
		{
			$params['context_order'] = 'left_id';
			$params['context_order_direction'] = 'ASC';
		}

		if ($params['context_order'] === 'manual')
		{
			$params['context_order'] = 'left_id';
		}

		$next_operator = strtolower($params['context_order_direction']) === 'asc' ? '>' : '<';
		$prev_operator = $next_operator === '>' ? '<' : '>';

		$arr = array();

		$next = new Album;
		$prev = new Album;

		if (isset($params['context']) && strpos($params['context'], 'tag-') === 0)
		{
			$tag = str_replace('tag-', '', urldecode($params['context']));
			$t = new Tag;
			$t->where('name', $tag)->get();

			if ($t->exists())
			{
				$prev->where('deleted', 0)
					->where('id !=', $this->id)
					->where('title <', $this->title)
					->where_related_tag('id', $t->id)
					->order_by('title DESC, id DESC');

				$next->where('deleted', 0)
					->where('id !=', $this->id)
					->where('title >', $this->title)
					->where_related_tag('id', $t->id)
					->order_by('title ASC, id ASC');

				$arr['type'] = 'tag';
				$arr['title'] = $tag;
				$arr['slug'] = $tag;

				$to_arr_options['context'] = "tag-$tag";

				$t->model = 'tag_albums';
				$t->slug = $t->name;
				$url = $t->url();

				if ($url)
				{
					list($arr['__koken_url'], $arr['url']) = $url;
				}
			}
		}
		else if (isset($params['context']) && strpos($params['context'], 'category-') === 0)
		{
			$category = str_replace('category-', '', $params['context']);
			$cat = new Category;
			$cat->where('slug', $category)->get();
			if ($cat->exists())
			{
				$prev->where('deleted', 0)
					->where('id !=', $this->id)
					->where('title <', $this->title)
					->where_related_category('id', $cat->id)
					->order_by('title DESC, id DESC');

				$next->where('deleted', 0)
					->where('id !=', $this->id)
					->where('title >', $this->title)
					->where_related_category('id', $cat->id)
					->order_by('title ASC, id ASC');

				$arr['type'] = 'category';
				$arr['title'] = $cat->title;
				$arr['slug'] = $cat->slug;

				$to_arr_options['context'] = "category-{$cat->id}";

				$cat->model = 'category_albums';
				$url = $cat->url();

				if ($url)
				{
					list($arr['__koken_url'], $arr['url']) = $url;
				}
			}
		}
		else
		{
			$prev->where('deleted', 0)
				->where("{$params['context_order']} $prev_operator", $this->{$params['context_order']})
				->where('level', $this->level)
				->order_by("{$params['context_order']} " . ($prev_operator === '<' ? 'DESC' : 'ASC'));

			$next
				->where('deleted', 0)
				->where("{$params['context_order']} $next_operator", $this->{$params['context_order']})
				->where('level', $this->level)
				->order_by("{$params['context_order']} {$params['context_order_direction']}");

			if ($this->level > 1)
			{
				$parent = new Album();
				$parent->select('left_id,right_id')
						->where('left_id <', $this->left_id)
						->where('level <', $this->level)
						->where('visibility', $this->visibility)
						->where('deleted', 0)
						->order_by('left_id DESC')
						->limit(1)
						->get();

				if ($parent->exists())
				{
					$next->where('left_id >', $parent->left_id);
					$next->where('right_id <', $parent->right_id);

					$prev->where('left_id >', $parent->left_id);
					$prev->where('right_id <', $parent->right_id);
				}
			}
		}

		if (!$auth)
		{
			$next->where('visibility', 0);
			$prev->where('visibility', 0);
		}

		if (!$params['include_empty_neighbors'])
		{
			$next->where('total_count >', 0);
			$prev->where('total_count >', 0);
		}

		$max = $next->get_clone()->count();
		$min = $prev->get_clone()->count();

		$arr['total'] = $max + $min + 1;
		$arr['position'] = $min + 1;
		$pre_limit = $next_limit = $n;

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

		$arr['previous'] = array();
		$arr['next'] = array();

		if ($next_limit > 0)
		{
			$next->limit($next_limit)->get_iterated();

			foreach($next as $a)
			{
				$arr['next'][] = $a->to_array( $to_arr_options );
			}
		}

		if ($pre_limit > 0)
		{
			$prev->limit($pre_limit)->get_iterated();

			foreach($prev as $a)
			{
				$arr['previous'][] = $a->to_array( $to_arr_options );
			}
			$arr['previous'] = array_reverse($arr['previous']);
		}

		return $arr;
	}
	function listing($params)
	{
		$sort = $this->_get_site_order('album');

		$options = array(
			'trash' => false,
			'page' => 1,
			'order_by' => $sort['by'],
			'order_direction' => $sort['direction'],
			'search' => false,
			'search_filter' => false,
			'tags' => false,
			'tags_not' => false,
			'match_all_tags' => false,
			'limit' => false,
			'include_empty' => true,
			'types' => false,
			'featured' => false,
			'category' => false,
			'category_not' => false,
			'year' => false,
			'year_not' => false,
			'month' => false,
			'month_not' => false,
			'day' => false,
			'day_not' => false,
			'flat' => false,
			'reduce' => false,
			'id_not' => false,
			'auth' => false,
		);
		$options = array_merge($options, $params);

		if (isset($params['order_by']) && !isset($params['order_direction']))
		{
			$options['order_direction'] = in_array($params['order_by'], array('created_on', 'modified_on', 'published_on', 'total_count', 'image_count', 'video_count')) ? 'DESC' : 'ASC';
		}

		$options = Shutter::filter('api.albums.listing.options', $options);
		Shutter::hook('albums.listing', array($this, $options));

		if ($options['order_by'] === 'manual')
		{
			$options['order_by'] = 'left_id';

			if (!isset($params['order_direction']))
			{
				$options['order_direction'] = 'asc';
			}
		}

		if ($options['featured'] == 1 && !isset($params['order_by']))
		{
			$options['order_by'] = 'featured_on';
		}

		if (!is_numeric($options['limit']))
		{
			$options['limit'] = false;
		}
		if ($options['types'])
		{
			$types = explode(',', str_replace(' ', '', $options['types']));

			$this->group_start();
			foreach($types as $t)
			{
				switch($t)
				{
					case 'set':
						$this->or_where('album_type', 2);
						break;

					case 'smart':
						$this->or_where('album_type', 1);
						break;

					case 'standard':
						$this->or_where('album_type', 0);
						break;
				}
			}
			$this->group_end();
		}

		if ($options['search'] && $options['search_filter'] === 'tags')
		{
			$options['tags'] = $options['search'];
			$options['search'] = false;
		}

		if ($options['search'])
		{
			$term = urldecode($options['search']);
			if ($options['search_filter'])
			{
				if ($options['search_filter'] === 'category')
				{
					$cat = new Category;
					$cat->where('title', $term)->get();
					if ($cat->exists())
					{
						$this->where_related('category', 'id', $cat->id);
					}
					else
					{
						$this->where_related('category', 'id', 0);
					}

				}
				else
				{
					$this->group_start();
					$this->like($options['search_filter'], $term, 'both');
					$this->group_end();
				}

			}
			else
			{
				$this->group_start();
				$this->like('title', $term, 'both');
				$this->or_like('description', $term, 'both');

				$t = new Tag;
				$t->where('name', $term)->get();

				if ($t->exists())
				{
					$this->or_where_related('tag', 'id', $t->id);
				}

				$this->group_end();
			}

		}
		else if ($options['tags'] || $options['tags_not'])
		{
			$this->_do_tag_filtering($options);
		}

		if ($options['id_not'])
		{
			$this->where_not_in('id', explode(',', $options['id_not']));
		}

		$sub_list = false;

		if ($this->exists())
		{
			$sub_list = true;
			$this->where('left_id >', $this->left_id)
					->where('right_id <', $this->right_id)
					->where('level', $this->level + 1)
					->where('visibility', $this->visibility);

			$options['visibility'] = $this->visibility;
		}
		else if ($options['auth'])
		{
			if (isset($options['visibility']))
			{
				$values = array('public', 'unlisted', 'private');
				if (in_array($options['visibility'], $values))
				{
					$options['visibility'] = array_search($options['visibility'], $values);
				}
				else
				{
					$options['visibility'] = 0;
				}
			}
			else
			{
				$options['visibility'] = 0;
			}
		}
		else
		{
			$options['visibility'] = 0;
		}

		$this->where('visibility', $options['visibility']);

		if ($options['visibility'] > 0 && ($options['order_by'] === 'manual' || $options['order_by'] === 'published_on'))
		{
			$options['order_by'] = 'title';
		}

		if (!$options['include_empty'])
		{
			$this->where('total_count >', 0);
		}
		if ($options['featured'] || $options['category'] || $options['category_not'])
		{
			if ($options['featured'])
			{
				$this->where('featured', 1);
			}
			if ($options['category'])
			{
				$this->where_related('category', 'id', $options['category']);
			}
			else if ($options['category_not'])
			{
				$cat = new Album;
				$cat->select('id')->where_related('category', 'id', $options['category_not'])->get_iterated();
				$cids = array();
				foreach($cat as $c)
				{
					$cids[] = $c->id;
				}
				$this->where_not_in('id', $cids);
			}
		}
		else if ($options['featured'] !== false && (int) $options['featured'] === 0)
		{
			$this->where('featured', 0);
		}
		else if (!$sub_list && !$options['category'] && !$options['tags'] && !$options['year'] && !$options['flat'])
		{
			$this->where('level', 1);
		}

		if ($options['order_by'] === 'left_id' && ($options['tags'] || $options['year']))
		{
			$options['order_by'] = 'title,id';
			$options['order_direction'] = 'asc';
		}

		if (in_array($options['order_by'], array('created_on', 'modified_on')))
		{
			$date_col = $options['order_by'];
		}
		else
		{
			$date_col = 'published_on';
		}

		$s = new Setting;
		$s->where('name', 'site_timezone')->get();
		$tz = new DateTimeZone($s->value);
		$offset = $tz->getOffset( new DateTime('now', new DateTimeZone('UTC')) );

		if ($offset === 0)
		{
			$shift = '';
		}
		else
		{
			$shift = ($offset < 0 ? '-' : '+') . abs($offset);
		}

		if ($options['year'] || $options['year_not'])
		{
			if ($options['year_not'])
			{
				$options['year'] = $options['year_not'];
				$compare = ' !=';
			}
			else
			{
				$compare = '';
			}
			$this->where('YEAR(FROM_UNIXTIME(' . $this->table . '.' . $date_col . $shift . '))' . $compare, $options['year']);
		}
		if ($options['month'] || $options['month_not'])
		{
			if ($options['month_not'])
			{
				$options['month'] = $options['month_not'];
				$compare = ' !=';
			}
			else
			{
				$compare = '';
			}
			$this->where('MONTH(FROM_UNIXTIME(' . $this->table . '.' . $date_col . $shift . '))' . $compare, $options['month']);
		}
		if ($options['day'] || $options['day_not'])
		{
			if ($options['day_not'])
			{
				$options['day'] = $options['day_not'];
				$compare = ' !=';
			}
			else
			{
				$compare = '';
			}
			$this->where('DAY(FROM_UNIXTIME(' . $this->table . '.' . $date_col . $shift . '))' . $compare, $options['day']);

			if ($options['reduce'])
			{
				$e = new Text;
				$e->select('id')
					->where('page_type', 0)
					->where('published', 1)
					->where('YEAR(FROM_UNIXTIME(' . $this->table . '.published_on' . $shift . '))', $options['year'])
					->where('MONTH(FROM_UNIXTIME(' . $this->table . '.published_on' . $shift . '))', $options['month'])
					->where('DAY(FROM_UNIXTIME(' . $this->table . '.published_on' . $shift . '))', $options['day'])
					->include_related('album', 'id')
					->get_iterated();

				$ids = array();
				foreach($e as $essay)
				{
					if ($essay->album_id)
					{
						$ids[] = $essay->album_id;
					}
				}

				if (!empty($ids))
				{
					$this->where_not_in('id', $ids);
				}

				$tops = $this->get_clone()->where('album_type', 2)->get_iterated();
				$lefts = array();

				foreach($tops as $set)
				{
					if ($set->right_id - $set->left_id > 1)
					$lefts = array_merge($lefts, range($set->left_id + 1, $set->right_id - 1));
				}

				if (!empty($lefts))
				{
					$this->where_not_in('left_id', $lefts);
				}
			}
		}

		$this->where('deleted', (int) $options['trash']);
		$set_count = $this->get_clone()->where('album_type', 2)->count();
		$final = $this->paginate($options);

		$this->include_related_count('text');
		$this->include_related_count('categories');

		if (preg_match('/_on$/', $options['order_by']))
		{
			$options['order_by'] .= ' ' . $options['order_direction'] . ',id ' . $options['order_direction'];
		}
		else
		{
			$options['order_by'] .= ' ' . $options['order_direction'];
		}

		$data = $this->order_by($options['order_by'])->get_iterated();
		if (!$options['limit'])
		{
			$final['per_page'] = $data->result_count();
			$final['total'] = $data->result_count();
		}

		$final['counts'] = array(
			'albums' => $final['total'] - $set_count,
			'sets' => $set_count,
			'total' => $final['total']
		);

		$final['albums'] = array();

		$final['sort'] = $sort;

		$tag_map = $this->_eager_load_tags($data);

		foreach($data as $album)
		{
			$tags = isset($tag_map['c' . $album->id]) ? $tag_map['c' . $album->id] : array();
			$params['eager_tags'] = $tags;
			$params['include_parent'] = !$sub_list;
			$final['albums'][] = $album->to_array($params);
		}
		return $final;
	}

	function reset_covers($whitelist = null, $blacklist = null)
	{
		if ($this->album_type == 0)
		{
			$count = $this->covers->count();
			if ($count > 2) { return; }

			$existing_ids = array();
			foreach($this->covers->get_iterated() as $f)
			{
				$existing_ids[] = $f->id;
			}

			if (!is_null($blacklist))
			{
				$existing_ids[] = $blacklist;
			}

			$next = $this->contents->select('id')
						->where('deleted', 0);

			if (is_null($whitelist))
			{
				$next->where('visibility', 0);
			}
			else
			{
				$next->group_start()
					->where('id', $whitelist)
					->or_where('visibility', 0)
				->group_end();
			}

			$next->group_start()
				->where('file_type', 0)
				->or_where('lg_preview !=', 'NULL')
			->group_end();

			if (!empty($existing_ids))
			{
				$next->where_not_in('id', $existing_ids);
			}

			$next->limit(3 - $count)->get_iterated();

			foreach($next as $n)
			{
				$this->save_cover($n);
			}
		}
	}

	function manage_content($content_id, $method = 'post', $match_album_visibility = false)
	{
		if (strpos($content_id, ',') !== FALSE)
		{
			$ids = explode(',', $content_id);
		}
		else
		{
			$ids = array($content_id);
		}

		$h = new History();

		if ($this->album_type == 0)
		{
			$c = new Content();
			$members = $this->contents->select('id,lg_preview')->get_iterated();
			$member_ids = array();
			foreach($members as $member)
			{
				$member_ids[] = $member->id;
			}
			$contents = $c->where_in('id', $ids)->order_by('id ASC')->get_iterated();

			$added_ids = array();

			foreach($contents as $content)
			{
				if (!$content->exists())
				{
					return false;
				}
				$covers_count = $this->covers->count();
				switch($method)
				{
					case 'post':
						if ($this->save($content))
						{
							if (!in_array($content->id, $member_ids))
							{
								if ($covers_count < 3 && ($content->visibility == 0 && ($content->file_type == 0 || $content->lg_preview)))
								{
									$this->save_cover($content);
								}

								$this->update_counts(false);
								$this->save();
								$this->set_join_field($content, 'order', $this->total_count);
							}
							$added_ids[] = $content->id;
						}
						break;
					case 'delete':
						if (in_array($content->id, $member_ids))
						{
							$this->delete($content);
							$this->delete_cover($content);
							$this->update('modified_on', time());
							$this->save();
							$this->reset_covers();
						}
						break;
				}
			}

			if (count($added_ids) && !is_null($this->visibility) && $match_album_visibility)
			{
				$change = new Content;
				$change->where_in('id', $added_ids)
					->where('visibility !=', $this->visibility)
					->where('visibility <', 2)
					->update(array('visibility' => $this->visibility));
			}

			if (count($ids) == 1)
			{
				$message = 'content:move';
				$c = $content->filename;
			}
			else
			{
				$message = 'content:move:multiple';
				$c = count($ids);
			}
		}
		else
		{
			$a = new Album();
			switch($method)
			{
				case 'post':
				case 'delete':

					$this->db->trans_begin();

					foreach($ids as $move_id)
					{
						$d = new Album();
						$dest_copy = $d->select('level,left_id,right_id')->get_by_id($this->id);
						$move_copy = $a->select('visibility,level,left_id,right_id')->get_by_id($move_id);

						if ($method == 'post')
						{
							$destination_left = $dest_copy->right_id;
							$delta = ($dest_copy->level - $move_copy->level) + 1;
							$delta = $delta >= 0 ? '+ ' . $delta : '- ' . abs($delta);
							$level_delta = 'level ' . $delta;

							if (isset($_POST['match_album_visibility']) && $_POST['match_album_visibility'] > 0)
							{
								$this->_do_match_visibility(array(
									'left_id' => $move_copy->left_id,
									'right_id' => $move_copy->right_id,
									'visibility' => $this->visibility,
								));
							}

						}
						else
						{
							// For removals, we simply move the object back to the root
							$max = new Album();
							$max->select_max('right_id')->get();
							$destination_left = $max->right_id;
							$level_delta = 'level - ' . abs(1 - $move_copy->level);
							$destination_left++;
						}

						$left = $move_copy->left_id;
						$right = $move_copy->right_id;
						$size = $right - $left + 1;

						$a->shift_tree_values($destination_left, $size, $this->visibility);

						if ($move_copy->left_id >= $destination_left && $move_copy->visibility == $this->visibility)
						{
							$left += $size;
							$right += $size;
						}

						$delta = $destination_left - $left;
						$delta = $delta >= 0 ? '+ ' . $delta : '- '. abs($delta);

						$a->where('left_id >=', $left)
								->where('right_id <=', $right)
								->where('visibility', $move_copy->visibility)
								->update(array(
									'left_id' => "left_id $delta",
									'right_id' => "right_id $delta",
									'visibility' => $this->visibility,
									'level' => $level_delta
								), false);

						$a->where('visibility', 1)->where('published_on', NULL)->update(array('published_on' => time()));

						$a->shift_tree_values($right + 1, -$size, $move_copy->visibility);
					}

					$this->update_set_counts();
					$this->db->trans_complete();
					break;
			}
			$message = "album:move";
			if (count($ids) > 1)
			{
				$message .= ':multiple';
			}
			$c = count($ids);
		}
		if ($method == 'delete')
		{
			$message = str_replace('move', 'remove', $message);
		}
		$h->message = array($message, $c, $this->title);
		$h->save();
	}

	function shift_tree_values($first, $delta, $visibility)
	{
		$delta = $delta >= 0 ? '+ ' . $delta : '- '. abs($delta);

		$this->where('left_id >=', $first)
				->where('visibility', $visibility)
				->update(array(
					'left_id' => "left_id $delta"
				), false);

		$this->where('right_id >=', $first)
				->where('visibility', $visibility)
				->update(array(
					'right_id' => "right_id $delta"
				), false);
	}

	function to_array($options = array())
	{
		$options = array_merge( array('with_covers' => true, 'auth' => false), $options );

		$koken_url_info = $this->config->item('koken_url_info');

		$exclude = array('deleted', 'total_count', 'video_count', 'featured_order', 'tags_old', 'old_slug');
		$dates = array('created_on', 'modified_on', 'featured_on', 'published_on');
		$strings = array('title', 'summary', 'description', 'koken_password_protect_password');

		$bools = array('featured');

		list($data, $public_fields) = $this->prepare_for_output($options, $exclude, $bools, $dates, $strings);

		if (!$options['auth'] && $data['visibility'] < 1) {
			unset($data['internal_id']);
		}

		if (!$data['featured'])
		{
			unset($data['featured_on']);
		}

		$sort = array();
		list($sort['by'], $sort['direction']) = explode(' ', $data['sort']);

		$data['sort'] = $sort;

		$data['__koken__'] = 'album';

		if (array_key_exists('album_type', $data))
		{
			switch($data['album_type'])
			{
				case 2:
					$data['album_type'] = 'set';
					break;
				case 1:
					$data['album_type'] = 'smart';
					break;
				default:
					$data['album_type'] = 'standard';
			}
		}

		if ($this->album_type == 2)
		{
			$sum = new Album();
			$sum->select_sum('total_count')
				->select_sum('video_count')
				->where('right_id <', $this->right_id)
				->where('left_id >', $this->left_id)
				->where('album_type', 0)
				->where('visibility', 0)
				->get();

			$data['counts'] = array(
				'total' => (int) $this->total_count,
				'videos' => (int) $sum->video_count,
				'images' => $sum->total_count - $sum->video_count
			);
		}
		else
		{
			$data['counts'] = array(
				'total' => (int) $this->total_count,
				'videos' => (int) $this->video_count,
				'images' => $this->total_count - $this->video_count
			);
		}

		$data['tags'] = $this->_get_tags_for_output($options);

		$data['categories'] = array(
			'count' => is_null($this->category_count) ? $this->categories->count() : (int) $this->category_count,
			'url' => $koken_url_info->base . 'api.php?/albums/' . $data['id'] . '/categories'
		);

		$data['topics'] = array(
			'count' => is_null($this->text_count) ? $this->text->count() : (int) $this->text_count,
			'url' => $koken_url_info->base . 'api.php?/albums/' . $data['id'] . '/topics'
		);

		if ($options['with_covers']) {

			$data['covers'] = $existing = array();

			$covers = $this->covers;

			if (isset($options['before']))
			{
				$covers->where('published_on <=', $options['before']);
				$data['__cover_hint_before'] = $options['before'];
			}

			$covers->include_related_count('albums', NULL, array('visibility' => 0));
			$covers->include_related_count('categories');

			foreach($covers->order_by("covers_{$this->db_join_prefix}albums_covers.id ASC")->get_iterated() as $f)
			{
				if ($f->exists())
				{
					$data['covers'][] = $f->to_array(array('in_album' => $this));
					$existing[] = $f->id;
				}
			}

			$covers_count_set = false;

			if ($this->album_type == 2)
			{
				$covers_count_set = $this->covers->count();
			}

			if ($covers_count_set !== false && $covers_count_set < 3)
			{
				$a = new Album();
				$ids = $a->select('id')
							->where('right_id <', $this->right_id)
							->where('left_id >', $this->left_id)
							->where('visibility', $this->visibility)
							->get_iterated();

				$id_arr = array();

				foreach($ids as $id)
				{
					$id_arr[] = $id->id;
				}

				if (!empty($id_arr))
				{
					$c = new Content();
					$q = "SELECT DISTINCT cover_id FROM {$this->db_join_prefix}albums_covers WHERE album_id IN (" . join(',', $id_arr) . ")";
					if (!empty($existing))
					{
						$q .= ' AND cover_id NOT IN(' . join(',', $existing) . ')';
					}
					$covers = $c->query($q . "GROUP BY album_id LIMIT " . (3 - $covers_count_set));

					$f_ids = array();
					foreach($covers as $f)
					{
						$f_ids[] = $f->cover_id;
					}

					if (!empty($f_ids))
					{
						$c->where_in('id', $f_ids)->get_iterated();
						foreach($c as $content)
						{
							// TODO: auth needs to be passed in here
							array_unshift($data['covers'], $content->to_array(array('in_album' => $this)));
						}
					}
				}
			}

			// Latest covers first
			$data['covers'] = array_reverse($data['covers']);

		}

		if (isset($options['order_by']) && in_array($options['order_by'], array( 'created_on', 'modified_on' )))
		{
			$data['date'] =& $data[ $options['order_by'] ];
		}
		else
		{
			$data['date'] =& $data['published_on'];
		}

		if ($data['level'] > 1 && (!array_key_exists('include_parent', $options) || $options['include_parent']))
		{
			$parent = new Album();
			$parent->where('left_id <', $data['left_id'])
					->where('level <', $data['level'])
					->where('visibility', $this->visibility)
					->where('deleted', 0)
					->order_by('left_id DESC')
					->limit(1)
					->get();

			$data['parent'] = $parent->to_array();
		}
		else if ($data['level'] == 1)
		{
			$data['parent'] = false;
		}

		$cat = isset($options['category']) ? $options['category'] : (isset($options['context']) && strpos($options['context'], 'category-') === 0 ? str_replace('category-', '', $options['context']) : false);

		if ($cat)
		{
			if (is_numeric($cat))
			{
				foreach($this->categories->get_iterated() as $c)
				{
					if ($c->id == $cat)
					{
						$cat = $c->slug;
						break;
					}
				}
			}
		}

		$data['url'] = $this->url(
			array(
				'date' => $data['published_on'],
				'tag' => isset($options['tags']) ? $options['tags'] : (isset($options['context']) && strpos($options['context'], 'tag-') === 0 ? str_replace('tag-', '', $options['context']) : false),
				'category' => $cat,
			)
		);

		if ($data['url'])
		{
			list($data['__koken_url'], $data['url']) = $data['url'];
			$data['canonical_url'] = $data['url'];
		}

		if (!$options['auth'] && $data['visibility'] > 0) {
			unset($data['url']);
		}

		if (array_key_exists('visibility', $data))
		{
			switch($data['visibility'])
			{
				case 1:
					$raw = 'unlisted';
					break;
				case 2:
					$raw = 'private';
					break;
				default:
					$raw = 'public';
					break;
			}

			$data['visibility'] = array(
				'raw' => $raw,
				'clean' => ucwords($raw)
			);

			$data['public'] = $raw === 'public';
		}

		return Shutter::filter('api.album', array( $data, $this, $options ));
	}

	function apply_smart_conditions($smart_rules, $options = array(), $limit_for_preview = false)
	{
		$content = new Content;
		$array = unserialize($smart_rules);
		$conditions = $array['conditions'];
		if (!empty($conditions))
		{
			if ($array['any_all'])
			{
				$content->group_start();
			}
			else
			{
				$content->or_group_start();
			}
			foreach($conditions as $c)
			{
				if (isset($c['bool']) && !$c['bool'])
				{
					$bool = ' NOT ';
				}
				else
				{
					$c['bool'] = true;
					$bool = '';
				}
				switch($c['type'])
				{
					case 'album':
						if (!empty($c['filter']) && is_numeric($c['filter']))
						{
							$content->where_related_album('id' . ($c['bool'] ? '' : '!='), $c['filter']);
						}
						break;
					case 'tag':
						if (!empty($c['input']))
						{
							$content->group_start();
							if ($c['bool'])
							{
								$method = 'like';
							}
							else
							{
								$method = 'not_like';
								$content->or_group_start();
							}
							$content->{$method}('tags', "{$c['input']},");
							if (!$c['bool'])
							{
								$content->where('tags IS NULL');
								$content->group_end();
							}
							if (is_numeric($c['filter']))
							{
								$content->where_related_album('id', $c['filter']);
							}
							$content->group_end();
						}
						break;
					case 'date':
						switch($c['modifier'])
						{
							// TODO: Time zone offsets
							case 'on':
								$start = strtotime($c['start'] . ' 00:00:00');
								$end = strtotime($c['start'] . ' 23:59:59');
								$content->where($c['column'] . "{$bool}BETWEEN $start AND $end");
								break;
							case 'before':
								$start = strtotime($c['start'] . ' 00:00:00');
								$content->group_start();
								$content->where($c['column'] . ' ' . ($c['bool'] ? '<' : '>'), $start)
										->where($c['column'] . ' IS NOT NULL')
										->where($c['column'] . ' <> 0');
								$content->group_end();
								break;
							case 'after':
								$start = strtotime($c['start'] . ' 23:59:59');
								$content->where($c['column'] . ' ' . ($c['bool'] ? '>' : '<'), $start);
								break;
							case 'between':
								$start = strtotime($c['start'] . ' 00:00:00');
								$end = strtotime($c['end'] . ' 23:59:59');
								$content->where($c['column'] . "{$bool}BETWEEN $start AND $end");
								break;
							case 'within':
								$end_str = date('Y-m-d') . ' 23:59:59';
								$end = strtotime($end_str);
								$start = strtotime($end_str . ' -' . $c['within'] . ' ' . $c['within_modifier'] . 's');
								$content->where($c['column'] . ' ' . ($c['bool'] ? '>' : '<'), $start);
								break;
						}
						break;
				}
			}
			$content->group_end();
			if (isset($array['limit_to']) && is_numeric($array['limit_to']))
			{
				$content->where('file_type', $array['limit_to']);
			}
			switch($array['order'])
			{
				case 'file':
					// TODO: Is this enough, or do we need to use natcasesort?
					$column = 'filename';
					break;
				default:
					if ($array['order'] == 'date')
					{
						$column = 'created_on';
					}
					else
					{
						$column = 'captured_on';
					}
					break;
			}
			$content->order_by($column . ' ' . $array['order_direction']);
			if (isset($options['limit']) && is_numeric($array['limit']))
			{
				if (!$options['limit'] || $array['limit'] < $options['limit'])
				{
					$options['limit'] = $array['limit'];
				}
				$options['cap'] = $array['limit'];
			}
		}
		if (empty($options))
		{
			$final = array();
		}
		else
		{
			$final = $content->paginate($options);
		}

		return array($content, $final);
	}
}

/* End of file album.php */
/* Location: ./application/models/album.php */
