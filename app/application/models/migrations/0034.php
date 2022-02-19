<?php

	$s = new Setting;
	$s->where('name', 'last_migration')->get();

	if (!$s->exists())
	{
		$t = new Tag;
		$fields = $this->db->query("SHOW COLUMNS FROM {$t->table} WHERE Field = 'id'");

		$value = '26';

		if ($fields)
		{
			$result = $fields->result();
			if ($result && strtolower($result[0]->Type) === 'int(9)')
			{
				$value = '34';
			}
		}

		$n = new Setting;
		$n->name = 'last_migration';
		$n->value = $value;
		$n->save();
	}

	$done = true;