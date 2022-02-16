<?php

	$s = new Setting;
	$s->where('name', 'site_url')->get();

	if (!$s->exists())
	{
		$n = new Setting;
		$n->name = 'site_url';
		$n->value = 'default';
		$n->save();
	}

	$done = true;