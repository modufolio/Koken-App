<?php

	$s = new Setting;
	$s->where('name', 'image_use_defaults')->get();

	$settings = array(
		'image_use_defaults' => 'true',
		'image_tiny_quality' => '80',
		'image_small_quality' => '80',
		'image_medium_quality' => '85',
		'image_medium_large_quality' => '85',
		'image_large_quality' => '85',
		'image_xlarge_quality' => '90',
		'image_huge_quality' => '90',
		'image_tiny_sharpening' => '0.6',
		'image_small_sharpening' => '0.5',
		'image_medium_sharpening' => '0.5',
		'image_medium_large_sharpening' => '0.5',
		'image_large_sharpening' => '0.5',
		'image_xlarge_sharpening' => '0.2',
		'image_huge_sharpening' => '0'
	);

	if (!$s->exists())
	{
		foreach($settings as $name => $value)
		{
			$u = new Setting;
			$u->name = $name;
			$u->value = $value;
			$u->save();
		}
	}

	delete_files( FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '000', true, 1 );

	$done = true;