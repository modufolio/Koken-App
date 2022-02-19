<?php

	$a = new Album;

	$fields = $this->db->query("SHOW COLUMNS FROM {$a->table}");
	$result = $fields->result();

	$columns = array();
	foreach($result as $field)
	{
		$columns[] = $field->Field;
	}

	if (in_array('tags_migrated', $columns))
	{
		foreach($a->where('tags_migrated', 0)->limit(50)->get_iterated() as $album)
		{
			$album->_format_tags(trim($album->tags_old, ','));
			$album->tags_migrated = 1;
			$album->save();
		}

		if ($a->where('tags_migrated', 0)->count() === 0)
		{
			$done = true;
		}
	}
	else
	{
		$done = true;
	}