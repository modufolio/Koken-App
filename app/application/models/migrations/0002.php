<?php

	$base_folder = preg_replace('/\/api\.php(.*)?$/', '', $_SERVER['SCRIPT_NAME']);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . $base_folder . '/api.php?/update/migrate/schema');
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_exec($curl);
	curl_close($curl);

	$s = new Setting;
	$s->where('name', 'site_hidpi')->get();

	if (!$s->exists())
	{
		$n = new Setting;
		$n->name = 'site_hidpi';
		$n->value = 'true';
		$n->save();
	}

	$done = true;