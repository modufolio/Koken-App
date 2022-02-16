<?php

	$s = new Setting;
	$s->where('name', 'site_page_title')->get();

	if (!$s->exists())
	{
		$title = new Setting;
		$title->where('name', 'site_title')->get();

		$page_title = new Setting;
		$page_title->name = 'site_page_title';
		$page_title->value = $title->value;
		$page_title->save();
	}

	$done = true;