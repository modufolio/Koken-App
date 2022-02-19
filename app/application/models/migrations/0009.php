<?php

	$s = new Setting;
	$s->where('name', 'retain_image_metadata')->get();

	if (!$s->exists())
	{
		$n = new Setting;
		$n->name = 'retain_image_metadata';
		$n->value = 'false';
		$n->save();
	}

	$done = true;