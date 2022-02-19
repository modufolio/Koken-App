<?php

class DDI_Settings extends KokenPlugin {

	function __construct()
	{
		$this->register_hook('content.create', 'after_content_create');
		$this->register_hook('content.update_with_upload', 'after_content_create');
	}

	function after_content_create($content)
	{
		$s = new Setting;
		$s->where('name', 'uploading_publish_on_captured_date')->get();

		if ($s->exists() && $s->value === 'true')
		{
			$fresh = new Content;
			$fresh->get_by_id($content['id']);
			$fresh->published_on = $content['captured_on']['utc'] ? $content['captured_on']['timestamp'] : 'captured_on';
			$fresh->save();
		}
	}
}