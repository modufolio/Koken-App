<?php

	$s = new Setting;
	$s->where('name', 'last_upload')->get();

	if (!$s->exists())
	{
		$n = new Setting;
		$n->name = 'last_upload';
		$n->value = 'false';
		$n->save();
	}

	$done = true;