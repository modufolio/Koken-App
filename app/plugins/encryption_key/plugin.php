<?php

class DDI_EncryptionKeyStore extends KokenPlugin implements KokenEncryptionKey {

	private $full_path;

	function __construct()
	{
		$this->register_encryption_key_handler();
		$this->full_path = $this->get_main_storage_path() . '/configuration/key.php';
	}

	function get()
	{
		if (file_exists($this->full_path))
		{
			if (!defined('BASEPATH'))
			{
				define('BASEPATH', true);
			}

			$config = include $this->full_path;

			if (isset($KOKEN_ENCRYPTION_KEY))
			{
				return $KOKEN_ENCRYPTION_KEY;
			}

			return $config;
		}

		return false;
	}

	function write($key)
	{
		$config = <<<FILE
<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// Do not edit or remove this file unless advised by Koken support
// support@koken.me

return '$key';
FILE;
		$this->make_child_dir(dirname($this->full_path));
		file_put_contents($this->full_path, $config);
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
}