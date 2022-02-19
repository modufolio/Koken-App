<?php

	$s = new Slug;
	$slug_count = $s->like('id', 'content.', 'after')->count();

	$c = new Content;
	$content_count = $c->count();

	if ($slug_count < $content_count)
	{
		$slugs = array();

		$c = new Content;
		foreach($c->select('slug')->get_iterated() as $content)
		{
			$slugs[] = "('content." . $content->slug . "')";
		}

		$slugs = join(', ', $slugs);
		$this->db->query("INSERT INTO {$s->table}(id) VALUES $slugs ON DUPLICATE KEY UPDATE id=id;");

	}

	$done = true;