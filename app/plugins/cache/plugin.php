<?php

class DDI_Cache extends KokenPlugin implements KokenCache {

	private $stamp;
	private $base_path;

	function __construct()
	{
		$this->register_cache_handler('all');

		$this->base_path = $this->get_main_storage_path() . '/cache/';
		$this->stamp = $this->base_path . 'api/stamp';

		if (!file_exists($this->stamp))
		{
			$this->make_child_dir(dirname($this->stamp));
			touch($this->stamp);
		}
	}

	private function make_full_path($path)
	{
		if ($path === 'plugins/compiled.cache')
		{
			$path = str_replace('compiled', $this->request_read_token(), $path);
		}

		if (strpos($path, 'api/') === 0)
		{
			return $this->base_path . 'api/' . md5($path) . '.cache';
		}

		return $this->base_path . $path;
	}

	function get($path, $lastModified = false)
	{
		$is_api = strpos($path, 'api') === 0;

		$path = $this->make_full_path($path);

		if (file_exists($path))
		{
			$cache_stamp = filemtime($this->stamp);

			$mtime = filemtime($path);

			if (!$is_api || $mtime > $cache_stamp)
			{
				if ($lastModified && strtotime($lastModified) && is_int($mtime) && strtotime($lastModified) >= $mtime) {
					return array(
						'status' => 304
					);
				}

				// Path traversal check
				$realpath = realpath($this->base_path);
				$realpathfile = realpath($path);

				if (!$realpathfile || strpos($realpathfile, $realpath) !== 0)
				{
					return false;
				}

				return array(
					'data' => file_get_contents($path),
					'status' => 200,
					'modified' => $mtime
				);
			}
		}

		return false;
	}

	function write($path, $content)
	{
		$full_path = $this->make_full_path($path);
		$this->make_child_dir(dirname($full_path));
		file_put_contents($full_path, $content);
	}

	function clear($path)
	{
		$full_path = $this->make_full_path($path);
		if (strpos($path, 'api') === 0)
		{
			touch($this->stamp);
		}
		else if (is_dir($full_path))
		{
			$this->delete_files($full_path, true, 1);
		}
		else if (file_exists($full_path))
		{
			unlink($full_path);
		}
	}

	private function make_child_dir($path)
	{
		// No need to continue if the directory already exists
		if (is_dir($path)) return true;

		// Make sure parent exists
		$parent = dirname($path);
		if (!is_dir($parent))
		{
			$this->make_child_dir($parent);
		}

		$created = false;
		$old = umask(0);

		// Try to create new directory with parent directory's permissions
		$permissions = substr(sprintf('%o', fileperms($parent)), -4);
		if (is_dir($path) || mkdir($path, octdec($permissions), true))
		{
			$created = true;
		}
		// If above doesn't work, chmod to 777 and try again
		else if (	$permissions == '0755' &&
					chmod($parent, 0777) &&
					mkdir($path, 0777, true)
				)
		{
			$created = true;
		}
		umask($old);
		return $created;
	}

	private function delete_files($path, $del_dir = FALSE, $level = 0)
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
						$this->delete_files($path.DIRECTORY_SEPARATOR.$filename, $del_dir, $level + 1);
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
}