<?php

class KokenPlugin {

	protected $data = array();
	protected $require_setup = false;
	public $database_fields = false;

	function after_setup()
	{
		return true;
	}

	function is_compatible()
	{
		return true;
	}

	function require_setup()
	{
		return $this->require_setup;
	}

	function confirm_setup()
	{
		return true;
	}

	function set_data($data)
	{
		$this->data = (object) array_merge((array) $this->data, (array) $data);
	}

	function save_data()
	{
		if (class_exists('Plugin'))
		{
			$p = new Plugin;
			$p->where('path', $this->get_key())->get();

			$p->data = serialize( (array) $this->get_data() );
			$p->save();
		}
		else
		{
			return false;
		}
	}

	function get_data()
	{
		return $this->data;
	}

	/* Following functions are "final" and cannot be overriden in plugin classes */
	final protected function redirect($url, $params = array())
	{
		Koken::redirect($url, $params);
	}

	final protected function add_body_class($class)
	{
		Shutter::add_body_class($class);
	}

	final protected function get_api($url, $authenticated = false)
	{
		if ($authenticated)
		{
			$url .= '/token:' . $this->request_read_token();
		}

		return Koken::api($url);
	}

	final protected function get_key()
	{
		$reflector = new ReflectionClass(get_class($this));
		return basename(dirname($reflector->getFileName()));
	}

	final protected function clear_image_cache($id = false)
	{
		$root = dirname(dirname(dirname(dirname(__FILE__))));
		include_once($root . '/app/helpers/file_helper.php');
		$path = $root . '/storage/cache/images';
		if ($id)
		{
			$padded_id = str_pad($id, 6, '0', STR_PAD_LEFT);
			$path .= '/' . substr($padded_id, 0, 3) . '/' . substr($padded_id, 3);
		}
		delete_files($path, true, 1);
	}

	final protected function root_path()
	{
		return dirname(dirname(dirname(dirname(__FILE__))));
	}

	final protected function get_main_storage_path()
	{
		return $this->root_path() . '/storage';
	}

	final protected function get_file_path()
	{
		$root = dirname(dirname(dirname(dirname(__FILE__))));
		return $root . '/storage/plugins/' . $this->get_key();
	}

	final protected function get_storage_path()
	{
		return $this->get_file_path() . '/storage';
	}

	final protected function get_path()
	{
		return Koken::$location['real_root_folder'] . '/storage/plugins/' . $this->get_key();
	}

	final protected function request_read_token()
	{
		return Shutter::get_encryption_key();
	}

	final protected function request_token()
	{
		if (class_exists('Application') && isset($_POST))
		{
			$a = new Application;
			$a->single_use = 1;
			$a->role = 'read-write';
			$a->token = koken_rand();
			$a->save();
			return $a->token;
		}
		else
		{
			return false;
		}
	}

	final protected function register_hook($hook, $method)
	{
		Shutter::register_hook($hook, array($this, $method));
	}

	final protected function register_filter($filter, $method)
	{
		Shutter::register_filter($filter, array($this, $method));
	}

	final protected function register_shortcode($shortcode, $method)
	{
		Shutter::register_shortcode($shortcode, array($this, $method));
	}

	final protected function register_site_script($path)
	{
		Shutter::register_site_script($path, $this);
	}

	final protected function register_cache_handler($target)
	{
		Shutter::register_cache_handler($this, $target);
	}

	final protected function register_email_handler($label)
	{
		Shutter::register_email_handler($this, $label);
	}

	final protected function register_db_config_handler()
	{
		Shutter::register_db_config_handler($this);
	}

	final protected function register_encryption_key_handler()
	{
		Shutter::register_encryption_key_handler($this);
	}

	final protected function register_storage_handler()
	{
		Shutter::register_storage_handler($this);
	}

	final protected function register_template_folder($path)
	{
		Shutter::register_template_folder($this, $path);
	}

	final protected function set_message($key, $msg)
	{
		Shutter::set_message($key, $msg);
	}

	final protected function deliver_email($from, $from_name, $subject, $message)
	{
		Shutter::email($from, $from_name, null, $subject, $message);
	}

	final protected function download_file($f, $to)
	{
		if (extension_loaded('curl')) {
			$cp = curl_init($f);
			$fp = fopen($to, "w+");
			if (!$fp) {
				curl_close($cp);
				return false;
			} else {
				curl_setopt($cp, CURLOPT_FILE, $fp);
				curl_exec($cp);
				$code = curl_getinfo($cp, CURLINFO_HTTP_CODE);
				curl_close($cp);
				fclose($fp);

				if ($code >= 400)
				{
					unlink($to);
					return false;
				}
			}
		} elseif (ini_get('allow_url_fopen')) {
			if (!copy($f, $to)) {
				return false;
			}
		}
		return true;
	}
}
