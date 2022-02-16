<?php

class Plugin extends DataMapper {

	function init($plugins)
	{
		$plugin = $this->_get_plugin($plugins);
		if ($plugin && isset($plugin['php_class_name']))
		{
			return Shutter::get_php_object($plugin['php_class_name']);
		}
		else
		{
			return false;
		}
	}

	function _get_plugin($plugins)
	{
		foreach ($plugins as $plugin) {
			if ($plugin['path'] === $this->path)
			{
				return $plugin;
				break;
			}
		}
		return false;
	}

	function run_plugin_method($method, $plugins, $arg = null)
	{
		$plugin = $this->_get_plugin($plugins);
		if ($plugin && isset($plugin['php_class_name']))
		{
			return Shutter::call_method($plugin['php_class_name'], $method, $arg);
		}
		return false;
	}

	function save_data($plugins, $data)
	{
		$plugin = $this->_get_plugin($plugins);

		if ($plugin && isset($plugin['data']))
		{
			$save_data = array();

			global $raw_input_data;

			foreach($data as $name => $val)
			{
				if (isset($plugin['data'][$name]))
				{
					$info = $plugin['data'][$name];
					if ($info['type'] === 'boolean')
					{
						$save_data[$name] = $val == 'true';
					}
					else if ($info['type'] === 'text' && isset($raw_input_data[$name]))
					{
						$save_data[$name] = $raw_input_data[$name];
					}
					else
					{
						$save_data[$name] = $val;
					}
				}
			}

			Shutter::call_method($plugin['php_class_name'], 'set_data', (object) $save_data);
			$this->data = serialize( (array) Shutter::call_method($plugin['php_class_name'], 'get_data'));
		}
	}
}

/* End of file application.php */
/* Location: ./application/models/plugin.php */