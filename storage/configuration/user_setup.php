<?php

	// Path to ImageMagick on your server, if it is in a non-standard location.
	// define('MAGICK_PATH', 'convert');

	// Path to ffmpeg on your server
	define('FFMPEG_PATH', 'ffmpeg');

	// By default, Koken makes requests in parallel to improve performance.
	// On some hosts, this can cause you to exceed your resource allotment.
	// Uncomment this line and/or lower the number if you are having issues.
	// define('MAX_PARALLEL_REQUESTS', 4);