<?php

	$t = new Text;

	$fields = $this->db->query("SHOW COLUMNS FROM {$t->table}");
	$result = $fields->result();

	$columns = array();
	foreach($result as $field)
	{
		$columns[] = $field->Field;
	}

	if (in_array('tags_migrated', $columns))
	{
		foreach($t->where('tags_migrated', 0)->limit(100)->get_iterated() as $text)
		{
			$text->_format_tags(trim($text->tags_old, ','));
			$text->tags_migrated = 1;
			$text->save();
		}

		if ($t->where('tags_migrated', 0)->count() === 0)
		{
			$done = true;
		}
	}
	else
	{
		$done = true;
	}