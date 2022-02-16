<?php

	$c = new Content;

	$fields = $this->db->query("SHOW COLUMNS FROM {$c->table}");
	$result = $fields->result();

	$columns = array();
	foreach($result as $field)
	{
		$columns[] = $field->Field;
	}

	if (in_array('tags_migrated', $columns))
	{
		foreach($c->where('tags_migrated', 0)->limit(100)->get_iterated() as $content)
		{
			$content->_format_tags(trim($content->tags_old, ','));
			$content->tags_migrated = 1;
			$content->save();
		}

		if ($c->where('tags_migrated', 0)->count() === 0)
		{
			$done = true;
		}
	}
	else
	{
		$done = true;
	}