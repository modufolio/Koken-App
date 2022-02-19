<?php

	$a = new Album;

	foreach($a->where('visibility >', 0)->get_iterated() as $album)
	{
		$album->update_counts();
	}

	$done = true;
