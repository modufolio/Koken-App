<?php

	date_default_timezone_set('UTC');

	ini_set('display_errors', 1);
	error_reporting(1);

	set_time_limit(30);

	$root = dirname(__FILE__);

	@include $root . '/storage/configuration/user_setup.php';
	require_once $root . '/app/koken/Shutter/Shutter.php';
	require_once $root . '/app/koken/Utils/KokenAPI.php';

	if (!defined('LOOPBACK_HOST_HEADER'))
	{
		define('LOOPBACK_HOST_HEADER', false);
	}

	Shutter::enable();
	Shutter::hook('image.boot');

	if (isset($_GET['path']))
	{
		$path = $_GET['path'];
	}
	else if (isset($_SERVER['QUERY_STRING']))
	{
		$path = urldecode($_SERVER['QUERY_STRING']);
	}
	else if (isset($_SERVER['PATH_INFO']))
	{
		$path = $_SERVER['PATH_INFO'];
	}
	else if (isset($_SERVER['REQUEST_URI']))
	{
		$path = preg_replace('/.*\/i.php/', '', $_SERVER['REQUEST_URI']);
	}

	$ds = DIRECTORY_SEPARATOR;

	$dl = $base64 = false;
	if (preg_match('/\.dl$/', $path))
	{
		$path = preg_replace('/\.dl$/', '', $path);
		$dl = true;
	}
	else if (preg_match('/\.64$/', $path))
	{
		$path = preg_replace('/\.64$/', '', $path);
		$base64 = true;
	}

	$cache_key = 'images' . $path;
	$lock = 'locks/' . str_replace('/', '_', substr($path, 1));

	if (is_callable('register_shutdown_function'))
	{
		function shutdown()
		{
			global $lock;
			Shutter::clear_cache($lock);
		}

		register_shutdown_function('shutdown');
	}

	$new = false;

	$waited = 0;

	$cache = Shutter::get_cache($cache_key, (!$dl && !$base64) ? getenv('HTTP_IF_MODIFIED_SINCE') : false);

	while (!$cache && !Shutter::get_cache($cache_key) && Shutter::get_cache($lock) && $waited < 5) {
		sleep(1);
		$waited++;
	}

	if ($waited > 0)
	{
		$cache = Shutter::get_cache($cache_key, getenv('HTTP_IF_MODIFIED_SINCE'));
	}

	$info = pathinfo($cache_key);
	$ext = $info['extension'];

	if (!$cache)
	{
		Shutter::write_cache($lock, '');

		$new = $preset = true;

		preg_match('/^\/((?:[0-9]{3}\/[0-9]{3})|custom)\/(.*)[,\/](tiny|small|medium|medium_large|large|xlarge|huge)\.(crop\.)?(2x\.)?(?:\d{9,10}\.)?(?P<ext>jpe?g|gif|png|svg)(\.dl|.64)?$/i', $path, $matches);

		// If $matches is empty, they are requesting a custom size
		if (empty($matches))
		{
			preg_match('/^\/((?:[0-9]{3}\/[0-9]{3})|custom)\/(.*)[,\/]([0-9]+)\.([0-9]+)\.([0-9]{1,3})\.([0-9]{1,3})\.(crop\.)?(2x\.)?(?:\d{9,10}\.)?(?P<ext>jpe?g|gif|png|svg)(\.dl|.64)?$/i', $path, $matches);
			$preset = false;
		}

		if (empty($matches))
		{
			// Bad request
			header('HTTP/1.1 403 Forbidden');
			exit;
		}

		$custom = $matches[1] === 'custom';

 		// No path traversing in file name
 		if (preg_match("/[^a-zA-Z0-9._-]/", $matches[2])) {
			header('HTTP/1.1 403 Forbidden');
			exit;
		}

		$KokenAPI = new KokenAPI;
		$settings = $KokenAPI->get('/settings');

		if ($custom)
		{
			$original = $root . $ds . 'storage' . $ds . 'custom' . $ds . preg_replace('/\-(jpe?g|gif|png)$/i', '.$1', $matches[2]);
			list($source_width, $source_height) = getimagesize($original);
		}
		else
		{
			$id = (int) str_replace('/', '', $matches[1]);
			$content = $KokenAPI->get('/content/' . $id);

			$original_info = pathinfo($content['filename']);

			if (!isset($content['html']) && strtolower($original_info['filename']) !== strtolower($matches[2]))
			{
				$KokenAPI->clear();
				header('HTTP/1.1 404 Not Found');
				exit;
			}

			if (isset($content['original']['preview']))
			{
				if (isset($content['original']['preview']['relative_url']))
				{
					$original = $root . $content['original']['preview']['relative_url'];
				}
				else
				{
					$original = $content['original']['preview']['url'];
				}

				$source_width = $content['original']['preview']['width'];
				$source_height = $content['original']['preview']['height'];
			}
			else
			{
				if (isset($content['original']['relative_url']))
				{
					$original = $root . $content['original']['relative_url'];
				}
				else
				{
					$original = $content['original']['url'];
				}

				$source_width = $content['width'];
				$source_height = $content['height'];
			}
		}

		$remoteSource = preg_match('~^https?://~', $original);

		$KokenAPI->clear();

		if ($remoteSource || file_exists($original))
		{
			if (!defined('MAGICK_PATH'))
			{
				define('MAGICK_PATH_FINAL', 'convert');
			}
			else if (strpos(strtolower(MAGICK_PATH), 'c:\\') !== false)
			{
				define('MAGICK_PATH_FINAL', '"' . MAGICK_PATH . '"');
			}
			else
			{
				define('MAGICK_PATH_FINAL', MAGICK_PATH);
			}

			require($root . $ds . 'app' . $ds . 'koken' . $ds . 'DarkroomUtils.php');

			if ($preset)
			{
				$preset_array = DarkroomUtils::$presets[$matches[3]];
				$w = $preset_array['width'];
				$h = $preset_array['height'];
				$q = $settings['image_' . $matches[3] . '_quality'];
				$sh = $settings['image_' . $matches[3] . '_sharpening'];
				$crop = !empty($matches[4]);
				$hires = !empty($matches[5]);
			}
			else
			{
				list(,,,$w,$h,$q,$sh,$crop) = $matches;
				$crop = (bool) $crop;
				$hires = !empty($matches[8]);
				$sh /= 100;
			}

			$d = DarkroomUtils::init($settings['image_processing_library']);

			// TODO: Fix these create_function calls once we go 5.3
			if ($settings['image_processing_library'] === 'imagick')
			{
				$d->beforeRender(function($imObject, $options, $content) { return Shutter::filter('darkroom.render.imagick', array($imObject, $options, $content));}, $content);
			}
			else if (strpos($settings['image_processing_library'], 'convert') !== false)
			{
				$d->beforeRender(function($cmd, $options, $content) {return Shutter::filter('darkroom.render.imagemagick', array($cmd, $options, $content));}, $content);
			}
			else
			{
				$d->beforeRender(function($gdObject, $options, $content) { return Shutter::filter('darkroom.render.gd', array($gdObject, $options, $content));}, $content);
			}

			$midsize = preg_replace('/\.' . $info['extension'] . '$/', '.1600.' . $info['extension'], $original);

			$d->read($original, $source_width, $source_height)
			  ->resize($w, $h, $crop)
			  ->quality($q)
			  ->sharpen($sh)
			  ->focus($content['focal_point']['x'], $content['focal_point']['y']);

			if ($remoteSource)
			{
				if (isset($content['original']['midsize']))
				{
					$d->alternate($content['original']['midsize']);
				}
			}
			else if (file_exists($midsize))
			{
				$d->alternate($midsize);
			}

			if ($hires)
			{
				$d->retina();
			}

			if (!$settings['retain_image_metadata'] || max($w, $h) < 480 || $settings['image_processing_library'] === 'gd')
			{
				// Work around issue with mbstring.func_overload = 2
				if ((ini_get('mbstring.func_overload') & 2) && function_exists('mb_internal_encoding')) {
					$previous_encoding = mb_internal_encoding();
					mb_internal_encoding('ISO-8859-1');
				}

				require($root . $ds . 'app' . $ds . 'koken' . $ds . 'icc.php');
				$icc = new JPEG_ICC;

				preg_match('~^(/\d{3}/\d{3}/)~', $path, $match);

				$icc_cache_key = 'icc' . $match[1] . 'profile.icc';
				$icc_cache = Shutter::get_cache($icc_cache_key);
				if ($icc_cache)
				{
					$icc->SetProfile($icc_cache['data']);
				}
				else
				{
					$icc->LoadFromJpeg($original);
					Shutter::write_cache($icc_cache_key, $icc->GetProfile());
				}

				$d->strip();
			}

			$blob = $d->render();

			if (isset($icc))
			{
				$blob = $icc->SaveToBlob($blob);
			}


			Shutter::hook('darkroom.render.complete', array($blob));
		}
		else
		{
			header('HTTP/1.1 404 Not Found');
			exit;
		}

		if (empty($blob))
		{
			header('HTTP/1.1 500 Internal Server Error');
			exit;
		}

	}

	Shutter::clear_cache($lock);

	if ($cache)
	{
		$mtime = $cache['modified'];
	}
	else
	{
		$mtime = time();
	}

	$etag = md5($cache_key . $mtime);

	$ext = strtolower($ext);

 	if ($ext == 'jpg')
 	{
 		$ext = 'jpeg';
 	}

	if ($cache) {
		if ($cache['status'] === 304)
		{
			$server_protocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : false;

			if (substr(php_sapi_name(), 0, 3) === 'cgi')
			{
				header('Status: 304 Not Modified', true, 304);
			}
			elseif ($server_protocol === 'HTTP/1.1' OR $server_protocol === 'HTTP/1.0')
			{
				header($server_protocol . ' 304 Not Modified', true, 304);
			}
			else
			{
				header('HTTP/1.1 304 Not Modified', true, 304);
			}
			exit;
		}
		else
		{
			$blob = $cache['data'];
		}
	}
	else
	{
		Shutter::write_cache($cache_key, $blob);
	}

	if ($dl)
	{
		header("Content-Disposition: attachment; filename=\"" . basename($cache_key) . "\"");
		header('Content-type: image/' . $ext);
		header('Content-length: ' . strlen($blob));

		die($blob);
	}
	else if ($base64)
	{
		$string = base64_encode($blob);
		die("data:image/$ext;base64,$string");
	}

	header('Content-type: image/' . $ext);
	header('Content-length: ' . strlen($blob));
	header('Cache-Control: public');
	header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime('+1 year')) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	header('ETag: ' . $etag);

	echo $blob;
