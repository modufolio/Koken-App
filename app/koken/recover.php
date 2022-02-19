<?php

	function delete_files($path, $del_dir = FALSE, $level = 0)
	{
		// Trim the trailing slash
		$path = rtrim($path, DIRECTORY_SEPARATOR);

		if ( ! $current_dir = @opendir($path))
		{
			return FALSE;
		}

		while (FALSE !== ($filename = @readdir($current_dir)))
		{
			if ($filename != "." and $filename != "..")
			{
				if (is_dir($path.DIRECTORY_SEPARATOR.$filename))
				{
					// Ignore empty folders
					if (substr($filename, 0, 1) != '.')
					{
						delete_files($path.DIRECTORY_SEPARATOR.$filename, $del_dir, $level + 1);
					}
				}
				else
				{
					unlink($path.DIRECTORY_SEPARATOR.$filename);
				}
			}
		}
		@closedir($current_dir);

		if ($del_dir == TRUE AND $level > 0)
		{
			return @rmdir($path);
		}

		return TRUE;
	}

	$movers = array('admin', 'app', 'api.php', 'i.php', 'index.php', 'preview.php', 'dl.php', 'a.php');

	foreach($movers as $m) {
		$to = dirname(__FILE__) . '/' . $m;
		$path = $to . '.off';

		if (file_exists($path))
		{
			if (file_exists($to))
			{
				delete_files($to, true, 1);
			}

			rename($path, $to);
		}
	}

	unlink(__FILE__);
