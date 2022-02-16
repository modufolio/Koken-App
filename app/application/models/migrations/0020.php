<?php

	$s = new Slug;
	$slug_count = $s->like('id', 'page.', 'after')->count();

	$c = new Text;
	$content_count = $c->where('page_type', 1)->count();

	if ($slug_count < $content_count)
	{
		$slugs = array();

		$c = new Text;
		foreach($c->where('page_type', 1)->select('slug')->get_iterated() as $content)
		{
			$slugs[] = "('page." . $content->slug . "')";
		}

		$slugs = join(', ', $slugs);
		$this->db->query("INSERT INTO {$s->table}(id) VALUES $slugs ON DUPLICATE KEY UPDATE id=id;");
	}

	$done = true;