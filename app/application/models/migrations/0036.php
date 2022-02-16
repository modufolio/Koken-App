<?php

$url = new Url;
$current = $url->order_by('id DESC')->limit(1)->get();

$config = unserialize($current->data);

$sort = 'manual ASC';

foreach ($config as $url_conf) {
	if ($url_conf['type'] === 'album') {
		$setSort = $url_conf['data']['order'];
	} else if ($url_conf['type'] === 'content' && isset($url_conf['data']['album_order'])) {
		$sort = $url_conf['data']['album_order'];
	}
}

$albums = new Album;
$albums->where('album_type', 0)->get();
$albums->update_all('sort', $sort);

$sets = new Album;
$sets->where('album_type', 2)->get();
$sets->update_all('sort', $setSort);

$done = true;