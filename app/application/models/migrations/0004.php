<?php

	$t = new Text();
	$t->update('content', "REPLACE(content, 'koken_custom_image', 'koken_upload')", FALSE);

	$done = true;