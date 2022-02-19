<?php

	$s = new Setting;
	$s->where('name', 'use_default_labels_links')->get();

	if (!$s->exists())
	{
		$u = new Setting;
		$u->name = 'use_default_labels_links';
		$u->value = 'false';
		$u->save();

		$urls = array(
			array(
				'type' => 'content',
				'data' => array(
					'singular' => 'Content',
					'plural' => 'Content',
					'order' => 'captured_on DESC',
					'url' => 'id',
				)
			),
			array(
				'type' => 'favorite',
				'data' => array(
					'singular' => 'Favorite',
					'plural' => 'Favorites',
					'order' => 'manual ASC'
				)
			),
			array(
				'type' => 'album',
				'data' => array(
					'singular' => 'Album',
					'plural' => 'Albums',
					'order' => 'manual ASC',
					'url' => 'id'
				)
			),
			array(
				'type' => 'set',
				'data' => array(
					'singular' => 'Set',
					'plural' => 'Sets',
				)
			),
			array(
				'type' => 'essay',
				'data' => array(
					'singular' => 'Essay',
					'plural' => 'Essays',
					'order' => 'published_on DESC',
					'url' => 'id'
				)
			),
			array(
				'type' => 'page',
				'data' => array(
					'singular' => 'Page',
					'plural' => 'Pages',
					'url' => 'id'
				)
			),
			array(
				'type' => 'tag',
				'data' => array(
					'singular' => 'Tag',
					'plural' => 'Tags'
				)
			),
			array(
				'type' => 'category',
				'data' => array(
					'singular' => 'Category',
					'plural' => 'Categories'
				)
			),
			array(
				'type' => 'archive',
				'data' => array(
					'singular' => 'Archive',
					'plural' => 'Archives'
				)
			)
		);

		$u = new Url;
		$u->data = serialize($urls);
		$u->save();
	}

	$done = true;