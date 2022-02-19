<?php

class System extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

    function clear_caches()
    {
    	$this->authenticate();
    	if (!$this->auth)
    	{
			$this->error('403', 'Access forbidden');
			return;
    	}
    	$this->_clear_system_caches();
    }

	function index()
	{
		// TODO: require auth for this controller
		// director-core will need some reworking
		// if (!$this->auth)
		// {
		// 	$this->error('401', 'The action requires an authentication token.');
			// return;
		// }

		list($params,) = $this->parse_params(func_get_args());

		include(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'ffmpeg.php');

		$ffmpeg = new FFmpeg;

		function max_upload_to_bytes($val)
		{
			$val = strtolower($val);
			if (preg_match('/(k|m|g)/', $val, $match))
			{
				$val = (int) str_replace($match[1], '', $val);
				switch($match[1])
				{
					case 'k':
						$val *= 1024;
						break;

					case 'm':
						$val *= 1048576;
						break;

					case 'g':
						$val *= 1073741824;
						break;
				}
				return $val;
			}
			else
			{
				return (int) $val;
			}
		}

		$max_upload = max_upload_to_bytes( ini_get('upload_max_filesize') );
		$post_max = max_upload_to_bytes( ini_get('post_max_size') );

		$max = min($max_upload, $post_max);

		if ($max >= 1024)
		{
			$max_clean = ($max / 1024) . 'KB';
		}

		if ($max >= 1048576)
		{
			$max_clean = ($max / 1048576) . 'MB';
		}

		if ($max >= 1073741824)
		{
			$max_clean = ($max / 1073741824) . 'GB';
		}

		$c = new Content();
		$c->select_max('modified_on')->get();
		$a = new Album();
		$a->select_max('modified_on')->get();
		$t = new Text();
		$t->select_max('modified_on')->get();

		$this->load->library('webhostwhois');

		$webhost = new WebhostWhois(array(
			'useDns' => false
		));

		if (!defined('MAX_PARALLEL_REQUESTS'))
		{
			// Hosts we know do not limit parallel requests
			$power_hosts = array(
				'dreamhost',
				'media-temple-grid',
				'go-daddy',
				'in-motion',
				'rackspace-cloud',
			);

			$parallel = in_array($webhost->key, $power_hosts) ? 8 : 3;
		}
		else
		{
			$parallel = MAX_PARALLEL_REQUESTS;
		}

		// TODO: Some of this info should be limited to authenticated sessions
		$data = array(
			'version' => KOKEN_VERSION,
			'operating_system' => PHP_OS,
			'memory_limit' => ini_get('memory_limit'),
			'auto_updates' => AUTO_UPDATE,
			'php_version' => PHP_VERSION,
			'exif_support' => is_really_callable('exif_read_data'),
			'iptc_support' => is_really_callable('iptcparse'),
			'ffmpeg_support' => is_really_callable('exec') ? $ffmpeg->version() : false,
			'upload_limit' => $max,
			'upload_limit_clean' => $max_clean,
			'timestamp' => (int) max($c->modified_on, $a->modified_on, $t->modified_on),
			'rewrite_enabled' => $this->check_for_rewrite(),
			'mysql_version' => $this->db->call_function('get_server_info', $this->db->conn_id),
			'max_parallel_requests' => $parallel,
			'webhost' => $webhost->key,
			'store' => KOKEN_STORE_URL,
			'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
		);

		$this->set_response_data($data);
	}
}

/* End of file system.php */
/* Location: ./system/application/controllers/system.php */