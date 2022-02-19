<?php

	$s = new Setting;
	$s->where('name', 'site_timezone')->get();
	$tz = new DateTimeZone($s->value);
	$offset = $tz->getOffset(new DateTime('now'));

	if (is_numeric($offset) && $offset !== 0)
	{
		if ($offset < 0)
		{
			$offset = ' - ' . abs($offset);
		}
		else
		{
			$offset = ' + ' . $offset;
		}

		$c = new Content;
		$this->db->query("UPDATE {$c->table} SET captured_on = captured_on $offset WHERE captured_on = uploaded_on");
	}

	$done = true;