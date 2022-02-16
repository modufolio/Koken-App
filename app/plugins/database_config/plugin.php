<?php

class DDI_DatabaseConfig extends KokenPlugin implements KokenDatabaseConfiguration {

	private $full_path;

	private $config = null;

	function __construct()
	{
		$this->register_db_config_handler();
		$this->full_path = $this->get_main_storage_path() . '/configuration/database.php';
	}

	private function set_driver()
	{
		$socket = strpos($this->config['hostname'], ':') !== false;
		$this->config['driver'] = function_exists('mysqli_connect') && !$socket ? 'mysqli' : 'mysql';
	}

	function get()
	{
		if (is_null($this->config))
		{
			$config = include $this->full_path;

			if (isset($KOKEN_DATABASE))
			{
				$config = $KOKEN_DATABASE;
			}

			$this->config = $config;
			$this->set_driver();
		}

		return $this->config;
	}

	function write($configuration)
	{
		$config = <<<CONF
<?php
	return array(
		'hostname' => '{$configuration['hostname']}',
		'database' => '{$configuration['database']}',
		'username' => '{$configuration['username']}',
		'password' => '{$configuration['password']}',
		'prefix' => '{$configuration['prefix']}',
		'socket' => '{$configuration['socket']}'
	);

CONF;
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
