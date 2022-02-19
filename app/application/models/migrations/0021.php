<?php

	$s = new Slug;
	$slug_count = $s->like('id', 'essay.', 'after')->count();

	$c = new Text;
	$content_count = $c->where('page_type', 0)->count();

	if ($slug_count < $content_count)
	{
		$slugs = array();

		$c = new Text;
		foreach($c->where('page_type', 0)->select('slug')->get_iterated() as $content)
		{
			$slugs[] = "('essay." . $content->slug . "')";
		}

		$slugs = join(', ', $slugs);
		$this->db->query("INSERT INTO {$s->table}(id) VALUES $slugs ON DUPLICATE KEY UPDATE id=id;");
	}

	$done = true;