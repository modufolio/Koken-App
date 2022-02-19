<?php

class Theme {

	function __get($key)
	{
		$CI =& get_instance();
		return $CI->$key;
	}

	function read($keys = false)
	{
		$dir = get_dir_file_info(FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'themes');
		$base_host = '//' . $_SERVER['HTTP_HOST'] . preg_replace('/api\.php(.*)?$/', '', $_SERVER['SCRIPT_NAME']);
		$base =  $base_host . 'storage/themes/';
		$final = array();

		foreach($dir as $key => $val)
		{
			$p = $val['server_path'];
			$path = basename($p);
			if (strpos($path, ' ') !== false) continue;
			$info = $p . DIRECTORY_SEPARATOR . 'info.json';
			$guid = $p . DIRECTORY_SEPARATOR . 'koken.guid';
			$guid_old = $p . DIRECTORY_SEPARATOR . '.guid';
			if (is_dir($p) && file_exists($info))
			{
				$info_array = json_decode( file_get_contents($info) );
				if ($info_array)
				{
					$preview = $p . DIRECTORY_SEPARATOR . 'preview.jpg';
					if (file_exists($preview))
					{
						$preview = $base . $key . '/preview.jpg';
					}
					else
					{
						$preview = str_replace('storage/themes', 'app/site/themes', $base) . '/preview.jpg';
					}
					list($w, $h) = getimagesize(FCPATH . str_replace($base_host, '', $preview));
					$a = array(
						'name' => $info_array->name,
						'version' => $info_array->version,
						'description' => $info_array->description,
						'demo' => isset($info_array->demo) ? $info_array->demo : false,
						'documentation' => isset($info_array->documentation) ? $info_array->documentation : false,
						'path' => $key,
						'preview' => $preview,
						'preview_aspect' => $w/$h,
						'author' => $info_array->author
					);

					if (file_exists($guid))
					{
						$a['koken_store_guid'] = file_get_contents($guid);
					}
					else if (file_exists($guid_old))
					{
						$a['koken_store_guid'] = file_get_contents($guid_old);
					}

					if ($keys)
					{
						$final[$key] = $a;
					}
					else
					{
						$final[] = $a;
					}
				}
			}
		}

		if (!$keys)
		{
			function sortByName($a, $b) {
				return $a['name'] > $b['name'];
			}

			usort($final, 'sortByName');
		}

		return $final;
	}

}