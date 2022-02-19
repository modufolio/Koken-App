<?php

	$themes = array(
		'axis' => '86d2f683-9f90-ca3f-d93f-a2e0a9d0a089',
		'blueprint' => '1a355994-6217-c7ce-b67a-4241be3feae8',
		'boulevard' => 'b30686d9-3490-9abb-1049-fe419a211502',
		'chastain' => 'd174e766-5a5f-19eb-d735-5b46ae673a6d',
		'elementary' => 'be1cb2d9-ed05-2d81-85b4-23282832eb84',
		'madison' => '618e0b9f-fba0-37eb-810a-6d615d0f0e08',
		'observatory' => '605ea246-fa37-11f0-f078-d54c8a7cbd3c',
		'regale' => 'efde04b6-657d-33b6-767d-67af8ef15e7b',
		'repertoire' => 'fa8a5d39-01a5-dfd6-92ff-65a22af5d5ac'
	);

	$themes_dir = FCPATH .
					'storage' . DIRECTORY_SEPARATOR .
					'themes' . DIRECTORY_SEPARATOR;

	foreach($themes as $name => $guid)
	{
		$dir = $themes_dir . $name;
		$guid_path = $dir . DIRECTORY_SEPARATOR . 'koken.guid';
		if (is_dir($dir) && !file_exists($guid_path))
		{
			file_put_contents($guid_path, $guid);
		}
	}

	$done = true;