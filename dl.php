<?php

	$path = $_GET['src'];

	if (preg_match('~/storage/originals/([a-z0-9]{2}/[a-z0-9]{2}/.*)$~', (string) $path, $matches))
	{
		$base = __DIR__ . '/storage/originals/';
		$full_path = $base . $matches[1];

		if (!file_exists($full_path))
		{
			header('HTTP/1.1 404 Not Found');
			exit;
		}

		$realbase = realpath($base);
		$realfile = realpath($full_path);

		if (!$realfile || !str_starts_with($realfile, $realbase))
		{
			header('HTTP/1.1 403 Forbidden');
			exit;
		}

		$name = basename((string) $path);
		$info = pathinfo($name);
		$ext = $info['extension'];

		header("Content-Disposition: attachment; filename=$name");
		$ct = match (strtolower($ext)) {
      'jpg' => 'image/jpeg',
      'gif' => 'image/gif',
      'png' => 'image/png',
      default => 'application/octet-stream',
  };

		header('Content-type: ' . $ct);
		header('Content-length: ' . filesize($full_path));

		$disabled_functions = explode(',', ini_get('disable_functions'));

		if (is_callable('readfile') && !in_array('readfile', $disabled_functions)) {
			readfile($full_path);
		} else {
			die(file_get_contents($full_path));
		}
	}
	else
	{
		header('HTTP/1.1 403 Forbidden');
	}