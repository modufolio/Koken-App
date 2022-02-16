<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Koken_Controller extends CI_Controller {

	var $method = 'get';
	var $auto_authenticate = true;
	var $strict_cookie_auth = true;
	var $callback = false;
	var $auth = false;
	var $auth_user_id;
	var $auth_token;
	var $auto_role;
	var $caching = true;
	var $purges_cache = true;
	var $cache_path;

	// If no format is specified, JSON it is
	var $format = 'json';

	// We can't use CI's set_output, as it causes type coersion, so we'll use our own var for that
	var $response_data = array();

	function _clear_system_caches()
    {
		Shutter::clear_cache(array('api', 'core', 'site', 'locks'));
		delete_files(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'datamapper' . DIRECTORY_SEPARATOR . 'cache', true, 1);

		$this->_compile_plugins();

		$a = new Album;
		$a->repair_tree();

    	$s = new Setting;
    	$s->where('name', 'site_url')->get();

    	if ($this->check_for_rewrite())
    	{
	    	if ($s->value === 'default')
	    	{
	    		$htaccess = create_htaccess();
	    		$root_htaccess = FCPATH . '.htaccess';
	    		$current = file_get_contents($root_htaccess);
	    		preg_match('/#MARK#.*/s', $htaccess, $match);
	    		$htaccess = preg_replace('/#MARK#.*/s', str_replace('$', '\\$', $match[0]), $current);
	    		file_put_contents($root_htaccess, $htaccess);
	    	}
	    	else
	    	{
	    		if (isset($_SERVER['PHP_SELF']) && isset($_SERVER['SCRIPT_FILENAME']))
				{
					$doc_root = str_replace( $_SERVER['PHP_SELF'], '', $_SERVER['SCRIPT_FILENAME']);
				}
				else
				{
					$doc_root = $_SERVER['DOCUMENT_ROOT'];
				}

				$doc_root = realpath($doc_root);
				$target = $doc_root . str_replace('/', DIRECTORY_SEPARATOR, $s->value);

				$htaccess = create_htaccess($s->value);

				$file = $target . DIRECTORY_SEPARATOR . '.htaccess';

				if (file_exists($file))
				{
					$existing = file_get_contents($file);
					if (strpos($existing, '#MARK#') !== false)
					{
			    		preg_match('/#MARK#.*/s', $htaccess, $match);
						$htaccess = preg_replace('/#MARK#.*/s', str_replace('$', '\\$', $match[0]), $existing);
					}
					else
					{
						$htaccess = $existing . "\n\n" . $htaccess;
					}
				}

				file_put_contents($file, $htaccess);

				if ("$doc_root" . DIRECTORY_SEPARATOR !== FCPATH)
				{
					$root_htaccess = FCPATH . '.htaccess';
					if (file_exists($root_htaccess))
					{
						$current = file_get_contents($root_htaccess);
						$redirect = create_htaccess($s->value, true);
						if (strpos($current, '#MARK#') !== false)
						{
							preg_match('/#MARK#.*/s', $redirect, $match);
							$redirect = preg_replace('/#MARK#.*/s', str_replace('$', '\\$', $match[0]), $current);
						}
						else
						{
							$redirect = $current . "\n\n" . $redirect;
						}
						file_put_contents($root_htaccess, $redirect);
					}
				}
	    	}
    	}
    }

	function _clear_datamapper_cache()
	{
		if (ENVIRONMENT === 'production')
		{
			delete_files(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'datamapper' . DIRECTORY_SEPARATOR . 'cache', false, 1);
		}
	}

	function _download($f, $to, $force_content_mimes = false)
	{
		if (extension_loaded('curl')) {
			$cp = curl_init($f);
			$fp = fopen($to, "w+");
			if (!$fp) {
				curl_close($cp);
				return false;
			} else {
				if (strpos($f, 'https://') === 0)
				{
					curl_setopt($cp, CURLOPT_SSL_VERIFYHOST, 2);
					curl_setopt($cp, CURLOPT_SSL_VERIFYPEER, false);
				}
				else if (!$force_content_mimes)
				{
					curl_setopt($cp, CURLOPT_HTTPHEADER, array(
						'Accept: application/octet-stream'
					));
				}
				curl_setopt($cp, CURLOPT_FILE, $fp);
				curl_setopt($cp, CURLOPT_CONNECTTIMEOUT, 15);
				curl_exec($cp);
				if ($force_content_mimes)
				{
					$mime = curl_getinfo($cp, CURLINFO_CONTENT_TYPE);
					if (!preg_match('/^(image|video|application)\/.*/', $mime))
					{
						curl_close($cp);
						fclose($fp);
						unlink($to);
						return false;
					}
				}
				curl_close($cp);
				fclose($fp);
			}
		} elseif (ini_get('allow_url_fopen')) {
			if (!copy($f, $to)) {
				return false;
			}
		}

		if ((!file_exists($to) || filesize($to) === 0) && preg_match('/^https:/', $f))
		{
			// Some hosts fail on the DNS for store.koken.me, so fallback to the AWS domain name over regular HTTP
			$f = str_replace('https://store.koken.me', 'http://production-rh4cavismp.elasticbeanstalk.com', $f);
			return $this->_download(str_replace('https://', 'http://', $f), $to, $force_content_mimes);
		}

		return true;
	}

	function __construct()
    {
		parent::__construct();

		$this->load->database();
		$this->load->library('datamapper');

		if (!$this->db->conn_id)
		{
			$this->error(500, 'Database connection failed. Make sure the database server is running and the information in storage / configuration / database.php is still correct.', true);
		}

		if (strlen($this->config->item('encryption_key')) !== 32)
		{
			$key = md5($_SERVER['HTTP_HOST'] . uniqid('', true));
			$this->config->set_item('encryption_key', $key);
			Shutter::write_encryption_key($key);
		}

		if (isset($_SERVER['HTTP_X_KOKEN_AUTH']) && $_SERVER['HTTP_X_KOKEN_AUTH'] === 'cookie')
		{
			$this->load->library('session');
		}

    	$this->check_for_rewrite();
    	$uri_parts = $this->uri->ruri_to_assoc(1);
    	$action = array_shift($uri_parts);

    	$this->cache_path = 'api' . $this->uri->uri_string();

		if ($this->input->is_cli_request())
		{
			$this->method = 'get';
		}
		else
		{
			$this->method = strtolower($_SERVER['REQUEST_METHOD']);
		}

		if ($this->auto_authenticate && is_array($this->auto_authenticate))
		{
			if (array_key_exists('exclude', $this->auto_authenticate))
			{
				if (in_array($action, $this->auto_authenticate['exclude']))
				{
					$this->auto_authenticate = false;
				}
			}
		}

		if ($this->auto_authenticate)
		{
			$auth = $this->authenticate();
			if ($auth)
			{
				$this->auth = true;
				list($this->auth_user_id, $this->auth_token, $this->auth_role) = $auth;
				if (strpos($this->cache_path, '/token:') === false && isset($this->auth_token))
				{
		    		$this->cache_path .= '/token:' . $this->auth_token;
				}
			}
		}

		$this->caching = !array_key_exists('cache:false', $uri_parts) && ($this->caching === true || (is_array($this->caching) && in_array($action, $this->caching)));

		$content_type = 'application/json';

		$user_setup = FCPATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'configuration' . DIRECTORY_SEPARATOR . 'user_setup.php';
		if ($this->uri->uri_string() === '/system' && file_exists($user_setup))
		{
			$this->cache_path .= '/' . filemtime($user_setup);
		}
		else if (preg_match('~/(js|css)$~', $this->uri->uri_string(), $content_type_match))
		{
			if ($content_type_match[1] === 'js')
			{
				$content_type = 'text/javascript';
			}
			else
			{
				$content_type = 'text/css';
			}
		}

		if ($this->method === 'get' && $this->caching)
		{
			$cache = Shutter::get_cache($this->cache_path, getenv('HTTP_IF_MODIFIED_SINCE'));

			if ($cache !== false)
			{
				if ($cache['status'] === 304)
				{
					set_status_header('304');
					exit;
				}

				if ($content_type !== 'application/json' || (!empty($cache['data']) && json_decode($cache['data'])))
				{
					header('Content-type: ' . $content_type);
					header('Cache-control: must-revalidate');
					header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $cache['modified']) . ' GMT');
					header('X-Koken-Cache: hit');
					die($cache['data']);
				}
			}
		}
		else if ($this->method !== 'get')
		{
			if ($this->auto_authenticate && (!$this->auth || $this->auth_role == 'read'))
			{
					$this->error('401', 'Not authorized to perform this action.', true);
			}

			if ($this->purges_cache && ENVIRONMENT === 'production')
			{
				Shutter::clear_cache(array('api', 'site'));
			}

			if (isset($_POST) && isset($_POST['_method']))
			{
				$this->method = strtolower($_POST['_method']);
				if (isset($_POST['model']))
				{
					$_POST = json_decode($_POST['model']);
				}
			}
		}

		// Force MySQL to UTC
		$this->db->simple_query("SET time_zone = '+00:00'");
    }

	function redirect($url)
	{
		if ($this->auth && $this->auth_role !== 'god')
		{
			$url .= '/token:' . $this->auth_token;
		}
		$info = $this->config->item('koken_url_info');

		header("Location: {$info->base}api.php?$url");
		exit;
	}

	function set_response_data($data)
	{
		$this->response_data = $data;
	}

	function add_to_history($message)
	{
		$h = new History();
		$h->message = $message;
		$h->save($this->auth_user_id);
	}

	function authenticate($require_king = false)
	{
		$token = false;
		$cookie = false;
		$cookie_auth = isset($_SERVER['HTTP_X_KOKEN_AUTH']) && $_SERVER['HTTP_X_KOKEN_AUTH'] === 'cookie';
		$this->load->helper('cookie');

		if (isset($_COOKIE['koken_session_ci']) && $cookie_auth)
		{
			$token = $this->session->userdata('token');
			if ($token)
			{
				$cookie = true;
			}
		}
		else if (isset($_COOKIE['koken_session']) && !$this->strict_cookie_auth)
		{
			$cookie = unserialize($_COOKIE['koken_session']);
			$token = $cookie['token'];
		}
		else if ($this->method == 'get' && preg_match("/token:([a-zA-Z0-9]{32})/", $this->uri->uri_string(), $matches))
		{
			// TODO: deprecate this in favor of X-KOKEN-TOKEN
			$token = $matches[1];
		}
		else if (isset($_REQUEST['token']))
		{
			$token = $_REQUEST['token'];
		}
		else if (isset($_SERVER['HTTP_X_KOKEN_TOKEN']))
		{
			$token = $_SERVER['HTTP_X_KOKEN_TOKEN'];
		}

		if ($token && $token ===  $this->config->item('encryption_key'))
		{
			return array(null, $token, null);
		}
		else if ($token)
		{
			$a = new Application();
			$a->where('token', $token)->limit(1)->get();

			if ($a->exists())
			{
				if ($a->role === 'god' && $this->strict_cookie_auth)
				{
					if (!$cookie)
					{
						return false;
					}
				}
				else
				{
					if ($a->single_use)
					{
						$a->delete();
					}
				}
				return array($a->user_id, $token, $a->role);
			}
		}
		else if ($cookie_auth && get_cookie('remember_me'))
		{
			$remember_token = get_cookie('remember_me');
			$u = new User;
			$u->where('remember_me', $remember_token)->get();
			if ($u->exists())
			{
				$token = $u->create_session($this->session, true);
				return array($u->id, $token, 'god');
			}
		}

		return false;
	}

	// Ignore $data, it will always be empty. Use our response_data var instead
	function _output($data, $code = 200)
	{
		switch($this->format)
		{
			// TODO: Other formats (XML, ATOM, Media RSS)?
			case 'php':
				$content_type = 'text/plain';
				$data = serialize($this->response_data);
				break;
			case 'javascript':
			case 'css':
				$content_type = 'text/' . $this->format;
				$data = $this->response_data;
				break;
			default:
				if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR'))
				{
					$data = json_encode($this->response_data, JSON_PARTIAL_OUTPUT_ON_ERROR);
				}
				else
				{
					$data = json_encode($this->response_data);
				}
				$content_type = 'json';
				if ($this->callback)
				{
					$content_type = 'javascript';
					$data = "{$this->callback}($data)";
				}
				$content_type = "application/$content_type";
				break;
		}
		set_status_header(is_array($this->response_data) && isset($this->response_data['error']) ? $this->response_data['http'] :$code);
		header("Content-type: $content_type");

		if ($this->caching && $this->cache_path && ENVIRONMENT === 'production' && $this->method === 'get' && (int) $code === 200)
		{
			Shutter::write_cache($this->cache_path, $data);
			header('Cache-control: must-revalidate');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
		}

		echo($data);
	}

	function parse_plugins()
	{
		$activated = new Plugin;
		$activated->get_iterated();
		$map = array();

		foreach($activated as $active)
		{
			if (!is_dir(FCPATH . 'storage/plugins/' . $active->path)) continue;

			$map[$active->path] = array('id' => $active->id, 'setup' => $active->setup == 1, 'data' => unserialize($active->data));
		}

		return Shutter::all($map);
	}

	function _compile_plugins()
	{
		$storage = FCPATH . 'storage/plugins/';

		$plugins = $this->parse_plugins();

		$to_compile = array('info' => array('email_handler' => 'DDI_Email'), 'plugins' => array());

		foreach($plugins as $plugin)
		{
			if (!$plugin['internal'] && $plugin['activated'] && $plugin['setup'])
			{
				$_arr = array('path' => $plugin['path']);

				if (isset($plugin['data']))
				{
					$data = array();
					foreach($plugin['data'] as $key => $arr)
					{
						if (isset($arr['value']))
						{
							$data[$key] = $arr['value'];
						}
					}
					$_arr['data'] = $data;
				}

				$to_compile['plugins'][] = $_arr;
			}
		}

		$s = new Setting;
		$s->where('name', 'email_handler')->get();

		if ($s->exists())
		{
			$to_compile['info']['email_handler'] = $s->value;
		}

		$s = new Setting;
		$s->where('name', 'email_delivery_address')->get();

		if ($s->exists())
		{
			$to_compile['info']['email_delivery_address'] = $s->value;
		}

		Shutter::write_cache('plugins/compiled.cache',  serialize($to_compile));
	}

	function error($code, $message = 'Error message not available.', $instant = false)
	{
		$this->response_data = array(
			'request' => $_SERVER['REQUEST_URI'],
			'error' => $message,
			'http' => $code
		);

		if ($instant)
		{
			$this->_output('', $code);
			exit;
		}
	}

	function check_for_rewrite()
	{
		if (defined('KOKEN_REWRITE'))
		{
			return KOKEN_REWRITE;
		}

		if (!file_exists(FCPATH . '.htaccess') && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') === 0)
		{
			define('KOKEN_REWRITE', false);
			return false;
		}

		$cache_key = 'core/rewrite_check';

		$cache = Shutter::get_cache($cache_key, false);

		if ($cache !== false)
		{
			define('KOKEN_REWRITE', trim($cache['data']) === 'on');
			return KOKEN_REWRITE;
		}

		$s = new Setting;
		$s->where('name', 'site_url')->get();

		if ($s->value === 'default')
		{
			$koken_url_info = $this->config->item('koken_url_info');
			$url = $koken_url_info->base . '__rewrite_test/';
		}
		else
		{
			$protocol = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
				$_SERVER['SERVER_PORT'] == 443 ||
				(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http';
			$url = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim($s->value, '/') . '/__rewrite_test/';
		}

		$headers = array(
			'Cache-Control: must-revalidate'
		);

		if (LOOPBACK_HOST_HEADER)
		{
			$host = $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'];
			$headers[] = 'Host: ' . $_SERVER['HTTP_HOST'];
			$url = str_replace($_SERVER['HTTP_HOST'], $host, $url);
		}

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		if (strpos($url, 'https://') === 0)
		{
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$return = trim(curl_exec($curl));
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		$rewrite_check = $code === 200 && $return === 'koken:rewrite';

		Shutter::write_cache($cache_key, $rewrite_check ? 'on' : 'off');

		define('KOKEN_REWRITE', $rewrite_check);

		return $rewrite_check;
	}

	function parse_params($args)
	{
		$params = $id = array();
		$allowed_string_ids = array('trash');

		if (count($args))
		{
			foreach($args as $index => $arg)
			{
				if (strpos($arg, ':') !== false)
				{
					$bits = explode(':', $arg);
					if (strpos($bits[1], '&') !== false)
					{
						// Upload URLs have extra query string, remove it here
						$bits[1] = substr($bits[1], 0, strpos($bits[1], '&'));
					}

					$bits[1] = urldecode($bits[1]);

					switch($bits[0])
					{
						case 'size':
							$params['size'][] = $bits[1];
							break;
						case 'preview':
							$params['preview'] = $bits[1];
							break;
						case 'callback':
							$this->callback = $bits[1];
							break;
						case 'format':
							$this->format = $bits[1];
							break;
						default:
							$params[$bits[0]] = $bits[1];
							break;
					}
				}
				else if (is_numeric($arg) || strpos($arg, ',') !== FALSE || strlen($arg) == 32 || preg_match('/\d{4}\-\d{1,2}\-\d{1,2}/', $arg) || in_array($arg, $allowed_string_ids))
				{
					$id[] = $arg;
				}
			}
		}
		if (count($id) == 0)
		{
			$id = null;
		}
		else if (count($id) == 1)
		{
			$id = $id[0];
		}

		// Security
		unset($params['auth']);

		if (is_null($id) && isset($params['slug']))
		{
			$slug = $params['slug'];
			unset($params['slug']);
		}
		else
		{
			$slug = false;
		}

		if (isset($params['draft_context']))
		{
			define('DRAFT_CONTEXT', $params['draft_context']);
		}
		return array($params, $id, $slug);
	}

	function aggregate($type, $options = array())
	{

		$options = array_merge(array('featured' => false), $options);

		$shared_params = array();

		if ($type === 'tag')
		{
			$shared_params['tags'] = $options['tag_slug'];
		}
		else if ($type === 'category')
		{
			$shared_params['category'] = $options['category'];
		}

		$album_params = $shared_params;
		$date_marker = false;

		if ($type === 'date')
		{
			$s = new Setting;
			$s->where('name', 'site_timezone')->get();
			$tz = new DateTimeZone($s->value);
			$offset = $tz->getOffset( new DateTime('now', new DateTimeZone('UTC')) );

			if ($offset === 0)
			{
				$shift = '';
			}
			else
			{
				$shift = ($offset < 0 ? '-' : '+') . abs($offset);
			}

			// Need to - the offset here, as we need to shift this timestamp by the inverse of the offset to match DB UTC time.
			// For example. Midnight in user's time (say, CT -5) is UTC+5.
			$album_params['before'] = $date_marker = strtotime("{$options['year']}-{$options['month']}-{$options['day']} 23:59:59") - $offset;
		}

		$aggregate = $essay_ids = $album_ids = $content_ids = $updated_album_ids = $exclude_albums = $exclude_content = $sets = $range = array();

		$t = new Text;
		$t->select('id, featured, featured_image_id, published_on')
			->where('page_type', 0)
			->where('published', 1);

		if ($type === 'date')
		{
			$t->where("YEAR(FROM_UNIXTIME({$t->table}.published_on{$shift}))", $options['year'])
				->where("MONTH(FROM_UNIXTIME({$t->table}.published_on{$shift}))", $options['month'])
				->where("DAY(FROM_UNIXTIME({$t->table}.published_on{$shift}))", $options['day']);
		}
		else if ($type === 'tag')
		{
			$t->where_related('tag', 'id', $options['tag']);
		}
		else
		{
			$t->where_related('category', 'id', $options['category']);
		}

		if ($options['featured'])
		{
			$t->where('featured', 1);
		}

		$t->include_related('album', 'id')
			->order_by($t->table . '.published_on DESC')
			->get_iterated();

		foreach($t as $essay)
		{
			$essay_ids[$essay->id] = $essay->published_on;
			$aggregate[] = array('type' => 'essay', 'id' => $essay->id, 'date' => $essay->published_on, 'featured' => $essay->featured);

			if ($essay->album_id)
			{
				$exclude_albums[] = $essay->album_id;
			}
			if (is_numeric($essay->featured_image_id))
			{
				$exclude_content[] = $essay->featured_image_id;
			}
		}

		$a = new Album;
		$a->select('id, featured, published_on, left_id, right_id, level')
			->where('visibility', 0)
			->where('deleted', 0)
			->where('total_count >', 0);

		if ($type === 'date')
		{
			$a->where("YEAR(FROM_UNIXTIME({$a->table}.published_on{$shift}))", $options['year'])
				->where("MONTH(FROM_UNIXTIME({$a->table}.published_on{$shift}))", $options['month'])
				->where("DAY(FROM_UNIXTIME({$a->table}.published_on{$shift}))", $options['day']);
		}
		else if ($type === 'tag')
		{
			$a->where_related('tag', 'id', $options['tag']);
		}
		else
		{
			$a->where_related('category', 'id', $options['category']);
		}

		if ($options['featured'])
		{
			$a->where('featured', 1);
		}

		$a->include_related('content', 'id')
			->order_by($a->table . '.published_on DESC')
			->get_iterated();

		foreach($a as $album)
		{
			if (is_numeric($album->content_id))
			{
				$exclude_content[] = $album->content_id;
			}

			if (!array_key_exists($album->id, $album_ids) && !in_array($album->id, $exclude_albums))
			{
				$album_ids[$album->id] = $album->published_on;
				$aggregate[] = array('type' => 'album', 'id' => $album->id, 'date' => $album->published_on, 'featured' => $album->featured);
			}

			if ($album->level < 2)
			{
				$range = array_merge($range, range($album->left_id, $album->right_id));
			}

			if ($album->level > 1)
			{
				$sets[$album->id] = $album->left_id;
			}
		}

		foreach($sets as $id => $left)
		{
			if (in_array($left, $range))
			{
				unset($album_ids[$id]);
				foreach($aggregate as $i => $info)
				{
					if ($info['type'] === 'album' && $info['id'] == $id)
					{
						unset($aggregate[$i]);
					}
				}
			}
		}

		$c = new Content;
		$c->select('id, published_on, featured');

		if (!empty($exclude_content))
		{
			$c->where_not_in('id', $exclude_content);
		}

		$c->where('visibility', 0)
			->where('deleted', 0);

		if ($type === 'date')
		{
			$c->include_related('album')->where("YEAR(FROM_UNIXTIME({$c->table}.published_on{$shift}))", $options['year'])
				->where("MONTH(FROM_UNIXTIME({$c->table}.published_on{$shift}))", $options['month'])
				->where("DAY(FROM_UNIXTIME({$c->table}.published_on{$shift}))", $options['day'])
				->group_start()
					->where($a->table. '.id', null)
					->or_where($a->table . '.deleted', 0)
				->group_end();
		}
		else if ($type === 'tag')
		{
			$c->where_related('tag', 'id', $options['tag']);
		}
		else
		{
			$c->where_related('category', 'id', $options['category']);
		}

		if ($options['featured'])
		{
			$c->where('featured', 1);
		}

		$c->order_by($c->table . '.published_on DESC')
			->get_iterated();

		foreach($c as $content)
		{
			if ($content->album_id && $content->album_visibility < 1 && $content->album_published_on <= $date_marker)
			{
				if (!isset($updated_album_ids[$content->album_id]))
				{
					$updated_album_ids[$content->album_id] = array(
						'items' => array($content->id),
						'date' => $content->published_on,
						'featured' => $content->album_featured
					);
				}
				else
				{
					$updated_album_ids[$content->album_id]['items'][] = $content->id;
					$updated_album_ids[$content->album_id]['date'] = max($content->published_on, $updated_album_ids[$content->album_id]['date']);
				}
			}
			else if (!$content->album_id)
			{
				$content_ids[$content->id] = $content->published_on;
				$aggregate[] = array('type' => 'content', 'id' => $content->id, 'date' => $content->published_on, 'featured' => $content->featured);
			}
		}

		foreach($updated_album_ids as $id => $a)
		{
			$aggregate[] = array('type' => 'updated_album', 'id' => $id, 'date' => $a['date'], 'featured' => $a['featured']);
		}

		$total = count($aggregate);

		if (!function_exists('_sort'))
		{
			function _sort($one, $two)
			{
				if ($one['featured'] && !$two['featured'])
				{
					return -1;
				}
				else if ($one['featured'] && $two['featured'])
				{
					return $one['date'] < $two['date'] ? 1 : -1;
				}
				return $two['featured'] || $one['date'] < $two['date'] || ($one['date'] === $two['date'] && $two['id'] > $one['id'] ) ? 1 : -1;
			}
		}

		usort($aggregate, '_sort');

		$stream = array(
			'page' => (int) isset($options['page']) ? (int) $options['page'] : 1,
			'pages' => (int) ceil($total/$options['limit']),
			'per_page' => (int) min($options['limit'], $total),
			'total' => (int) $total
		);

		$load = array_slice($aggregate, ($stream['page']-1)*$options['limit'], $options['limit']);

		$counts = array(
			'essays' => count($essay_ids),
			'albums' => count($album_ids),
			'content' => count($content_ids)
		);

		$counts['total'] = $counts['essays'] + $counts['albums'] + $counts['content'];

		$updated_album_ids_arr = $updated_album_ids;

		$essay_ids = $album_ids = $content_ids = $updated_album_ids = $final = $index = array();
		foreach($load as $i => $item)
		{
			$index[$item['type'] . '-' . $item['id']] = $i;
			${$item['type'] . '_ids'}[] = $item['id'];
		}

		if (!empty($essay_ids))
		{
			$e = new Text;
			$e->where_in('id', $essay_ids)->get_iterated();

			foreach($e as $essay)
			{
				$final[ $index['essay-' . $essay->id] ] = $essay->to_array($shared_params);
			}
		}

		if (!empty($album_ids))
		{
			$a = new Album;
			$a->where_in('id', $album_ids)->get_iterated();

			foreach($a as $album)
			{
				$final[ $index['album-' . $album->id] ] = $album->to_array($album_params);
			}
		}

		if (!empty($content_ids))
		{
			$c = new Content;
			$c->where_in('id', $content_ids)->get_iterated();

			foreach($c as $content)
			{
				$final[ $index['content-' . $content->id] ] = $content->to_array(
					array_merge($shared_params, array('order_by' => 'published_on'))
				);
			}
		}

		if (!empty($updated_album_ids))
		{
			$a = new Album;
			$a->where_in('id', $updated_album_ids)->get_iterated();

			foreach($a as $album)
			{
				$arr = $album->to_array();
				$arr['event_type'] = 'album_update';
				$arr['content'] = array();

				$info = $updated_album_ids_arr[$album->id];
				$c = new Content;
				$c->where_in('id', $info['items'])->order_by('published_on DESC')->get_iterated();

				foreach($c as $i => $content)
				{
					$carr = $content->to_array(array('order_by' => 'published_on', 'in_album' => $album));
					if ($i === 0)
					{
						$arr['date'] = $carr['date'];
					}
					$arr['content'][] = $carr;
				}

				$final[ $index['updated_album-' . $album->id] ] = $arr;
			}
		}

		ksort($final);

		$stream['items'] = array_values($final);

		return array( $stream, $counts );
	}

}
