<?php

class KokenAPI {
	private $curl;
	private $token;
	private $protocol = 'http';
	private $cache_dir;

	function __construct()
	{
		$this->token = Shutter::get_encryption_key();

		$this->curl = curl_init();

		$this->protocol = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
			$_SERVER['SERVER_PORT'] == 443 ||
			(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http';
	}

	public function get($url)
	{
		$url .= '/token:' . $this->token;

		$cache = Shutter::get_cache('api/' . $url);

		if ($cache)
		{
			$data = json_decode($cache['data'], true);
		}
		else
		{
			$headers = array(
				'Connection: Keep-Alive',
				'Keep-Alive: 2',
				'Cache-Control: must-revalidate'
			);

			if (LOOPBACK_HOST_HEADER)
			{
				$host = $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'];
				$headers[] = 'Host: ' . $_SERVER['HTTP_HOST'];
			}
			else
			{
				$host = $_SERVER['HTTP_HOST'];
			}

			$url = $this->protocol . '://' . $host . preg_replace('~/(app/site/site|(api|i|a))\.php.*~', "/api.php?$url", $_SERVER['SCRIPT_NAME']);

			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_HEADER, 0);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($this->curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1944.0 Safari/537.36');
			curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($this->curl, CURLOPT_TIMEOUT, 10);

			curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);

			if ($this->protocol === 'https')
			{
				curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
			}

			$data = json_decode( curl_exec($this->curl), true );
		}

		return $data;
	}

	public function clear()
	{
		curl_close($this->curl);
	}
}