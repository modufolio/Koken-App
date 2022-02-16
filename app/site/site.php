<?php

	error_reporting(0);
	define('KOKEN_VERSION', '0.22.24');
	define('BASEPATH', true);

	ini_set('default_charset', 'UTF-8');

	if (function_exists('mb_internal_encoding'))
	{
		mb_internal_encoding('UTF-8');
	}

	$root_path = dirname(dirname(dirname(__FILE__)));
	@include $root_path . '/storage/configuration/user_setup.php';
	require $root_path . '/app/koken/Shutter/Shutter.php';

	if (!defined('LOOPBACK_HOST_HEADER'))
	{
		define('LOOPBACK_HOST_HEADER', false);
	}

	Shutter::enable();
	Shutter::hook('site.boot');

	// If this isn't set, they have enabled URL rewriting for purty links and arrived here directly
	// (not through /index.php/this/that)
	if (!isset($rewrite))
	{
		$rewrite = true;
		$raw_url = $_GET['url'];
	}
	else
	{
		if (isset($_SERVER['QUERY_STRING']) && ( strpos($_SERVER['QUERY_STRING'], '/') === 0 || strpos($_SERVER['QUERY_STRING'], '%2F') === 0))
		{
			$raw_url = $_SERVER['QUERY_STRING'];
		}
		else
		{
			$raw_url = '/';
		}
	}

	$url_vars = array(
		'__overrides' => array(),
		'__overrides_display' => array(),
		'page' => 1
	);

	$to_replace = array();
	if (preg_match('~(page/(\d+)/)(&.*)?$~', $raw_url, $page_match))
	{
		$url_vars['page'] = $page_match[2];
	}

	if (preg_match_all('~([a-z_]+):([^/]+)~', $raw_url, $override_matches))
	{
		date_default_timezone_set('UTC');
		$full_match = array_shift($override_matches);
		$to_replace = array_merge($to_replace, $full_match);
		$date = array();

		foreach($override_matches[0] as $i => $val)
		{
			$url_vars['__overrides'][$val] = $override_matches[1][$i];
			$filter_val = str_replace('_', ' ', $override_matches[1][$i]);
		}
	}

	$preview = $pjax = false;

	if (isset($_SERVER['HTTP_X_PJAX']) || strpos($_SERVER['QUERY_STRING'], '_pjax=') !== false)
	{
		$pjax = true;
	}

	if (!isset($draft))
	{
		$draft = false;
	}
	else if ($draft && isset($_GET['preview']))
	{
		$preview = $_GET['preview'];
	}

	if ($draft)
	{
		$basename = 'preview.php';
	}
	else
	{
		$basename = 'index.php';
	}

	if ($rewrite)
	{
		$real_base_folder = preg_replace('~/app/site/site\.php(.*)?$~', '', $_SERVER['SCRIPT_NAME']);
		$base_path = isset($_GET['base_folder']) ? $_GET['base_folder'] : $real_base_folder;
		if ($base_path === '/')
		{
			$base_path = '';
		}
		$base_folder = $base_path;
	}
	else
	{
		$basename_regex = str_replace('.', '\\.', $basename);
		$base_folder = preg_replace("~/$basename_regex(.*)?$~", '', $_SERVER['SCRIPT_NAME']);
		$base_path = $base_folder . "/$basename?";

		if (!isset($real_base_folder))
		{
			$real_base_folder = $base_folder;
		}
	}

	if ($draft && !$preview && !isset($_COOKIE['koken_session_ci']))
	{
		header("Location: $base_folder/admin/#/site");
		exit;
	}

	if ($rewrite)
	{
		$url = $raw_url;
	}
	else
	{
		$url = preg_replace('/([\?&].*$)/', '', urldecode($raw_url));
	}

	if (empty($url))
	{
		$url = $raw_url = '/';
	}

	if ($url[strlen($url)-1] !== '/' && strpos($url, '.') === false)
	{
		header("HTTP/1.1 301 Moved Permanently");
		// Rewrite non-trailing slash URLs to trailing slash for SEO purposes.
		if ($rewrite)
		{
			$canon = "{$base_folder}$url/";

			$gets = array();
			foreach($_GET as $key => $val)
			{
				if (!empty($val) && $key !== 'url')
				{
					$gets[] = $key . '=' . $val;
				}
			}

			if (!empty($gets))
			{
				$canon .= '?' . join('&', $gets);
			}
		}
		else
		{
			$canon = $_SERVER['PHP_SELF'] . "?$url/";

			foreach($_GET as $key => $val)
			{
				if (!empty($val))
				{
					$canon .= '&' . $key . '=' . $val;
				}
			}
		}
		header("Location: $canon");
		exit;
	}

	if ($rewrite && preg_match('~/__rewrite_test/?$~', $url))
	{
		die('koken:rewrite');
	}

	$ds = DIRECTORY_SEPARATOR;

	if ($url === '/')
	{
		$cache_url = '/index';
	}
	else
	{
		$cache_url = $url;
	}

	if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']))
	{
		$parts = explode('&', $_SERVER['QUERY_STRING']);

		foreach($parts as $p)
		{
			if (strpos($p, '/') === 0) continue;

			if (strpos($p, '=') === false)
			{
				$url_vars[$p] = true;
			}
			else
			{
				list($key, $val) = explode('=', $p);
				$url_vars[$key] = urldecode($val);
			}
		}
	}

	$here = str_replace('/page/' . $url_vars['page'], '', $url);
	$url = preg_replace('~/+~', '/', str_replace($to_replace, '', $here));

	if (empty($url))
	{
		$url = '/';
	}

	if (empty($here))
	{
		$here = '/';
	}

	require 'Koken.php';

	$protocol = Koken::find_protocol();
	$original_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . preg_replace('/\?.*$/', '', $_SERVER['SCRIPT_NAME']) . '?' . $url;

	Koken::start();
	Koken::$protocol = $protocol;
	Koken::$original_url = $original_url;
	Koken::$root_path = $root_path;
	Koken::$draft = $draft;
	Koken::$preview = $preview;
	Koken::$rewrite = $rewrite;
	Koken::$pjax = $pjax;

	Koken::$location = array(
		'root' => $base_path,
		'root_folder' => $base_folder,
		'real_root_folder' => $real_base_folder,
		'here' => $here,
		'rewrite' => $rewrite,
		'parameters' => $url_vars,
		'host' => $protocol . '://' . $_SERVER['HTTP_HOST'],
		'hostname' => $_SERVER['HTTP_HOST'],
		'site_url' => $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_folder,
		'preview' => $preview,
		'draft' => $draft
	);

	Koken::$rss_feeds = array(
		'contents' => "$base_path/feed/content/recent.rss",
		'essays' => "$base_path/feed/essays/recent.rss",
		'timeline' => "$base_path/feed/timeline/recent.rss",
	);

	Shutter::hook('site.url', array($url));

	// Enable caching in case .htaccess missed it or isn't available
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!$draft || $preview) && !isset($_GET['default_link']) && !isset($_COOKIE['share_to_tumblr']))
	{
		$cache_url = rtrim($cache_url, '/');

		$css = $js = false;

		if (preg_match('/\.css\.lens$/', $cache_url))
		{
			$css = true;
			$cache_url = $base_path . $cache_url;
		}
		else if ($cache_url === '/koken.js')
		{
			$js = true;
			$cache_url = $base_path . $cache_url;
		}
		else if (!preg_match('/\.rss$/', $cache_url))
		{
			$cache_url = $base_path . preg_replace('/\?|&|=/', '_', preg_replace('/\?|&_pjax=[^&$]+/', '', urldecode(rtrim($cache_url, '/')))) . '/cache';
		}

		if ($preview)
		{
			$cache_url = '/__preview/' . $preview . $cache_url;
		}

		$cache_path = 'site' . str_replace('/', DIRECTORY_SEPARATOR, $cache_url) . ( $css || $js || preg_match('/\.rss$/', $cache_url) ? '' : ( $pjax ? '.phtml' : '.html' ) );

		$cache_path = Shutter::filter('site.cache.read.path', $cache_path);

		$cache = Shutter::get_cache($cache_path);

		if ($cache)
		{
			if ($css)
			{
				header('Content-type: text/css');
			}
			else if ($js)
			{
				header('Content-type: text/javascript');
			}
			else if (preg_match('/\.rss$/', $cache_url))
			{
				header('Content-type: application/rss+xml; charset=UTF-8');
			}
			else
			{
				header('Content-type: text/html; charset=UTF-8');
			}

			$mtime = $cache['modified'];

			if ($cache['status'] === 304) {
				header("HTTP/1.1 304 Not Modified");
				exit;
			}

			header('Cache-control: must-revalidate');
			header('X-Koken-Cache: hit');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $cache['modified']) . ' GMT');

			die($cache['data']);
		}
	}

	require $root_path . '/app/application/libraries/webhostwhois.php';

	if (!defined('MAX_PARALLEL_REQUESTS'))
	{
		// Hosts we know do not limit parallel requests
		$power_hosts = array(
			'dreamhost',
			'media-temple-gs',
			'go-daddy',
			'in-motion',
			'rackspace-cloud',
			'site5',
		);

		$webhost = new WebhostWhois(array('useDns' => false));

		if (in_array($webhost->key, $power_hosts))
		{
			define('MAX_PARALLEL_REQUESTS', 8);
		}
		else
		{
			define('MAX_PARALLEL_REQUESTS', 4);
		}
	}

	define('MAX_PARALLEL_REQUESTS_SITE', MAX_PARALLEL_REQUESTS - 1);

	$date = array();

	foreach(Koken::$location['parameters']['__overrides'] as $key => $val)
	{
		if (in_array($key, array('year', 'month', 'day')))
		{
			$date[$key] = $val;
		}
		else if ($key !== 'order_by')
		{
			if ($key === 'category' && is_numeric($val))
			{
				$category = Koken::api('/categories/' . $val);
				$val = $category['title'];
			}
			Koken::$location['parameters']['__overrides_display'][] = array(
				'title' => ucfirst(str_replace('_', ' ', $key)),
				'value' => $val
			);
		}
	}

	if (!empty($date) && isset($date['year']))
	{
		$str = $date['year'] . '-';
		$format = 'Y';
		if (isset($date['month']))
		{
			$str .= $date['month'] . '-';
			$format = 'F Y';
			if (isset($date['day']))
			{
				$str .= $date['day'];
				$format = 'F j, Y';
			}
			else
			{
				$str .= '01';
			}
		}
		else
		{
			$str .= '01-01';
		}

		Koken::$location['parameters']['__overrides_display'][] = array(
			'title' => 'Date',
			'value' => date($format, strtotime($str))
		);
	}

	// Fallback path with default themes
	Koken::$fallback_path = $root_path . $ds . 'app' . $ds . 'site' . $ds . 'themes';

	if (isset($cache_path))
	{
		Koken::$cache_path = $cache_path;
	}

	list($site_api, $categories) = Koken::api( array(
			'/site' . ( $draft ? ( $preview ? '/preview:' . $preview : '/draft:true') : '' ),
			'/categories',
		)
	);

	# Do this separately to be sure KOKEN_ENCRYPTION_KEY has been created by the above API call
	$koken_key = Shutter::get_encryption_key();
	$video = Koken::api('/content/types:video/limit:1/visibility:any/token:' . $koken_key);

	Koken::$has_video = count($video['content']) > 0;

	if (!is_array($site_api))
	{
		die( file_get_contents(Koken::$fallback_path . $ds . 'error' . $ds . 'api.html') );
	}

	if (isset($site_api['error']))
	{
		die( str_replace('<!-- ERROR -->', $site_api['error'], file_get_contents(Koken::$fallback_path . $ds . 'error' . $ds . 'json.html')) );
	}

	Koken::$site = $site_api;
	Koken::$profile = Koken::$site['profile'];
	Koken::$location['theme_path'] = $real_base_folder . '/storage/themes/' . $site_api['theme']['path'];

	foreach(Koken::$site['routes'] as $route)
	{
		if (isset($route['template']) && isset($route['path']))
		{
			Koken::$template_routes[$route['template']] = $route['path'];
		}
	}

	foreach($categories['categories'] as $c)
	{
		Koken::$categories[strtolower($c['title'])] = $c['id'];
	}

	if (isset($_GET['default_link']))
	{
		$location = Koken::$site['default_links'][$_GET['default_link']];
		unset($_GET['default_link']);
		foreach($_GET as $key => $val)
		{
			$location = str_replace(":$key", $val, $location);
		}
		header("Location: {$base_folder}$location");
	}

	date_default_timezone_set(Koken::$site['timezone']);

	// Setup path to current theme
	Koken::$template_path = $root_path . $ds .'storage' . $ds . 'themes' . $ds . Koken::$site['theme']['path'];

	$nav = array();

	if (isset(Koken::$site['navigation']))
	{
		foreach(Koken::$site['navigation']['items'] as &$n)
		{
			if (isset($n['front']) && $n['front'])
			{
				Koken::$navigation_home_path = rtrim($n['path'], '/') . '/';
			}
		}

		if (isset(Koken::$site['navigation']['groups']))
		{
			$groups = array();
			foreach(Koken::$site['navigation']['groups'] as $g)
			{
				$key = $g['key'];
				$groups[$key] = array(
					'items' => $g['items'],
					'items_nested' => $g['items_nested']
				);
			}
			Koken::$site['navigation']['groups'] = $groups;
		}

	}

	$temp = array();

	if (isset(Koken::$site['pulse_flat']))
	{
		Koken::$site['pulse'] = array();

		foreach(Koken::$site['pulse_flat'] as $key => $obj)
		{
			if ($obj['type'] === 'boolean')
			{
				$val = (bool) $obj['value'] ? 'true' : 'false';
			}
			else if (is_numeric($obj['value']))
			{
				$val = $obj['value'];
			}
			else
			{
				$val = "'{$obj['value']}'";
			}
			Koken::$site['pulse'][$key] = $val;
		}
	}

	$page_types = array();

	foreach(Koken::$site['templates'] as $arr)
	{
		$page_types[$arr['path']] = $arr['info'];
	}

	$page_types['lightbox'] = array( 'source' => 'content' );

	if (file_exists(Koken::$template_path . $ds . 'error.lens'))
	{
		$routes = array('/error/:code/' => array( 'template' => 'error' ));
		$http_error = false;
	}
	else
	{
		$routes = array();
		$http_error = true;
	}

	$lightbox = false;
	$redirects = array();

	// Create routes array
	foreach(Koken::$site['routes'] as $arr)
	{
		if (strpos($arr['path'], '.') === false)
		{
			$arr['path'] = rtrim($arr['path'], '/') . '/';
		}

		$r = array(
			'template' => $arr['template'],
			'source' => isset($arr['source']) ? $arr['source'] : false,
			'filters' => isset($arr['filters']) ? $arr['filters'] : false,
			'vars' => isset($arr['variables']) ? $arr['variables'] : false,
			'label' => isset($arr['label']) ? $arr['label'] : false,
		);

		if (strpos($arr['template'], 'redirect:') === 0)
		{
			$redirects[$arr['path']] = $r;
		}
		else
		{
			$routes[$arr['path']] = $r;
		}
	}

	if (!isset($routes['/']))
	{
		$routes['/'] = array(
			'template' => 'index',
			'source' => false,
			'filters' => false,
			'vars' => false
		);
	}

	$routes += $redirects;

	Koken::$location['urls'] = Koken::$site['urls'];

	$routed_variables = array();

	if ($url === '/')
	{
		if (Koken::$navigation_home_path)
		{
			$url = Koken::$navigation_home_path;
		}
		else if (Koken::$site['default_front_page'])
		{
			$url = Koken::$site['urls'][Koken::$site['default_front_page']];
		}
	}

	$stylesheet = $source = false;

	if (preg_match('/\.css\.lens$/', $url))
	{
		$final_path = 'css' . preg_replace('/\.lens$/', '', $url);
		$stylesheet = true;
		$variables_to_pass[] = array();
		$variables_to_pass[] = array();
	}
	else if ($url === '/koken.js')
	{
		$contents = file_get_contents(Koken::get_path('common/js/koken-dependencies.js'));
		$contents .= file_get_contents(Koken::get_path('common/js/koken.js'));

		$tmp = Koken::$location;
		foreach(Koken::$dynamic_location_parts as $key)
		{
			unset($tmp[$key]);
		}

		$contents .= '$K.location = ' . json_encode($tmp) . ';$K.lazy.max = ' . (is_numeric(MAX_PARALLEL_REQUESTS) ? MAX_PARALLEL_REQUESTS : 4) . ';';

		$image_defaults = array();
		foreach(array('tiny', 'small', 'medium', 'medium_large', 'large', 'xlarge', 'huge') as $preset)
		{
			$image_defaults[$preset] = array(
				'quality' => Koken::$site["image_{$preset}_quality"],
				'sharpening' => Koken::$site["image_{$preset}_sharpening"]
			);
		}
		$image_defaults = json_encode($image_defaults);

		$contents .= "\$K.imageDefaults = $image_defaults;";

		$hdpi = Koken::$site['hidpi'] ? 'true' : 'false';

		$contents .= "\$K.theme = '" . Koken::$site['theme']['path'] . "';\$K.retinaEnabled = $hdpi;\n";
		$contents .= "\$K.dateFormats = { date: \"" . Koken::$site['date_format'] . "\", time: \"" . Koken::$site['time_format'] . "\" };";

		if (Koken::$has_video)
		{
			$contents .= file_get_contents(Koken::get_path('common/js/mediaelement-and-player.min.js'));
		}

		$contents .= file_get_contents(Koken::get_path('common/js/pulse.js'));

		$pulse_obj = array();
		$pulse_srcs = array();
		foreach(Shutter::$active_pulse_plugins as $arr)
		{
			$pulse_obj[] = "'{$arr['key']}': '{$arr['path']}'";
			$pulse_srcs[] = $arr['path'];
		}
		$pulse_str = '';
		if (!empty($pulse_obj))
		{
			$pulse_obj = join(', ', $pulse_obj);
			$contents .= "\n\$K.pulse.plugins = { $pulse_obj };\n";

			foreach($pulse_srcs as $src)
			{
				$contents .= file_get_contents($root_path . $src);
			}
		}

		$lang = isset(Koken::$site['settings_flat']['language']['value']) ? Koken::$site['settings_flat']['language']['value'] : false;
		if ($lang && $lang !== 'en') {
			$contents .= file_get_contents(Koken::get_path("common/js/timeago/locales/jquery.timeago.{$lang}.js"));
		}

		$contents .= join("\n", Shutter::get_site_scripts());

		Koken::cache($contents);
		header('Content-type: text/javascript');
		die($contents);
	}
	else
	{
		// Loop through template defined routes and match URL
		foreach($routes as $route => $page)
		{
			// Find magic :name variables in the route
			preg_match_all('/(\:[a-z_-]+)/', $route, $variables);

			// We need to save the matched variables so we can reassign them after the match
			$match_variables = array();
			if (!empty($variables[0]))
			{
				foreach($variables[1] as $str)
				{
					// Save variable name for later
					$match_variables[] = str_replace(':', '', $str);
					// Replace magic :name variable with regular expression
					if ($str === ':year')
					{
						$pattern = '[0-9]{4}';
					}
					else if ($str === ':month' || $str === ':day')
					{
						$pattern = '[0-9]{1,2}';
					}
					else if ($str === ':id' || $str === ':content_id' || $str === ':album_id')
					{
						$pattern = '(?:(?:[0-9]+)|(?:[0-9a-z]{32}))';
					}
					else if ($str === ':code')
					{
						$pattern = '[0-9]{3}';
					}
					else
					{
						// Some servers don't report PCRE_VERSION as defined even though it is. LOLPHP
						if (@PCRE_VERSION !== 'PCRE_VERSION' && version_compare(PCRE_VERSION, '5.0.0') >= 0)
						{
							$pattern = '\d*[\-_\sa-z\.\p{L}][\-\s_a-z\.0-9\p{L}]*';
						}
						else
						{
							$pattern = '\d*[\-_\sa-z\.][\-\s_a-z\.0-9]*';
						}
					}
					$route = str_replace($str, "($pattern)", $route);
				}
			}

			if (preg_match('~^' . $route . '$~u', $url, $matches))
			{
				if (strpos($page['template'], 'redirect:') === 0)
				{
					$redirect = str_replace('redirect:', '', $page['template']);
				}
				else
				{
					$redirect = false;
				}

				if (isset($matches['lightbox']))
				{
					$final_path = 'lightbox';
				}
				else
				{
					$final_path = $page['template'];
				}

				$info = isset($page_types[$page['template']]) ? $page_types[$page['template']] : array();

				if (!empty($matches[1]))
				{
					foreach($match_variables as $index => $name)
					{
						// For some reason double urldecoding is necessary for rewritten URLs
						if (isset($matches[$index+1]) && !empty($matches[$index+1]))
						{
							$routed_variables[$name] = urldecode(urldecode($matches[$index+1]));
						}
					}

					$identifiers = array(
						'id', 'slug', 'content_id', 'content_slug', 'album_id', 'album_slug'
					);

					foreach ($identifiers as $identifier) {
						if (isset($routed_variables[$identifier]) && preg_match('/[a-z0-9]{32}/', $routed_variables[$identifier]))
						{
							Koken::$public = false;
							break;
						}
					}
				}

				if ($redirect)
				{

					if (strpos($redirect, 'soft:') !== false)
					{
						$redirect_type = '302 Moved Temporarily';
						$redirect = str_replace('soft:', '', $redirect);
					}
					else
					{
						$redirect_type = '301 Moved Permanently';
					}

					if (strpos($redirect, '/') === 0)
					{
						$redirect_to = $redirect;
					}
					else
					{
						$redirect_to = Koken::$site['urls'][$redirect];
					}

					foreach($routed_variables as $key => $val)
					{
						$redirect_to = str_replace(':' . $key, $val, $redirect_to);
					}

					$redirect_to = str_replace('(?:', '', $redirect_to);
					$redirect_to = str_replace(')?', '', $redirect_to);
					$redirect_to = str_replace('/:month', '', $redirect_to);

					if (strpos($redirect_to, ':') !== false)
					{
						if (isset($routed_variables['id']))
						{
							$id = $routed_variables['id'];
						}
						else
						{
							$id = 'slug:' . $routed_variables['slug'];
						}

						switch ($redirect) {
							case 'album':
								$url = '/albums';
								break;

							case 'essay':
							case 'page':
								$url = '/text';
								break;

							default:
								$url = '/content';
								break;
						}
						$url .= "/$id";
						$data = Koken::api($url);
						$redirect_to = $data['__koken_url'];
					}

					if ($redirect_to === '/timeline/' && isset($routed_variables['year']))
					{
						$redirect_to .= $routed_variables['year'];

						if (isset($routed_variables['month']))
						{
							$redirect_to .= '/' . $routed_variables['month'];
						}

						$redirect_to .= '/';
					}

					if (isset($routed_variables['album_id']) || isset($routed_variables['album_slug']))
					{
						$data = Koken::api('/content/' . ( isset($routed_variables['id']) ? $routed_variables['id'] : 'slug:' . $routed_variables['slug'] ) . '/context:' . ( isset($routed_variables['album_slug']) ? $routed_variables['album_slug'] : $routed_variables['album_id'] ));
						$redirect_to = $data['__koken_url'];
					}

					if (isset($matches['lightbox']))
					{
						$redirect_to .= '/lightbox';
					}

					$redirect_to = Koken::$location['root'] . $redirect_to . ( Koken::$preview ? '&amp;preview=' . Koken::$preview : '' );

					header("HTTP/1.1 $redirect_type");
					header("Location: $redirect_to");
					exit;
				}

				if (isset($routed_variables['content_slug']) || isset($routed_variables['content_id']))
				{
					if (isset($routed_variables['id']))
					{
						$routed_variables['album_id'] = $routed_variables['id'];
						unset($routed_variables['id']);
					}
					else
					{
						$routed_variables['album_slug'] = $routed_variables['slug'];
						unset($routed_variables['slug']);
					}

					if (isset($routed_variables['content_id']))
					{
						$routed_variables['id'] = $routed_variables['content_id'];
						unset($routed_variables['content_id']);
					}
					else
					{
						$routed_variables['slug'] = $routed_variables['content_slug'];
						unset($routed_variables['content_slug']);
					}

					if ($final_path !== 'lightbox')
					{
						$final_path = 'content';
					}
					$page['source'] = 'content';
					$page['filters'] = array();
				}
				else
				{
					if ($final_path === 'lightbox' && isset($info['source']) && $info['source'] === 'album')
					{
						$id = isset($routed_variables['id']) ? $routed_variables['id'] : 'slug:' . $routed_variables['slug'];
						$album = Koken::api('/albums/' . $id . '/content');
						$url = $album['content'][0]['url'] . '/lightbox';
						header("Location: $url");
						exit;
					}
				}

				if (isset($matches['template']))
				{
					foreach(Koken::$site['url_data'] as $key => $data)
					{
						if (isset($data['plural']) && $matches['template'] === strtolower($data['plural']))
						{
							$type = $key . 's';
							$final_path .= '.' . $type;
							$page['filters'] = array( "members=$type" );
							break;
						}
					}
				}

				$load = $source = isset($page['source']) && $page['source'] ? $page['source'] : ( isset($info['source']) ? $info['source'] : false );
				$filters = isset($page['filters']) && is_array($page['filters']) ? $page['filters'] : ( isset($info['filters']) ? $info['filters'] : false );

				if ($load)
				{
					if ($filters)
					{
						foreach($filters as &$f)
						{
							if (strpos($f, ':') !== false)
							{
								$f = preg_replace_callback("/:([a-z_]+)/",
										create_function(
											'$matches',
											'return Koken::$routed_variables[$matches[1]];'
										), $f);
							}
						}
					}
					Koken::$source = array( 'type' => $load, 'filters' => $filters );
				}

				Koken::$page_class = $page['template'] === 'index' ? 'k-source-index' : ( Koken::$source ? 'k-source-' . Koken::$source['type'] : '' );
				if (in_array(Koken::$page_class, array('k-source-tag', 'k-source-category', 'k-source-archive')) && Koken::$source['filters'] && count(Koken::$source['filters']))
				{
					Koken::$page_class = 'k-source-archive-' . str_replace('members=', '', Koken::$source['filters'][0]);
				}
				else if (Koken::$page_class === 'k-source-event')
				{
					Koken::$page_class = 'k-source-day-timeline';
				}

				if (Koken::$page_class === 'k-source-timeline' && isset($routed_variables['year']))
				{
					Koken::$page_class = 'k-source-archive-timeline';
				}

				Koken::$location['template'] = str_replace('.', '-', $page['template']);
				Koken::$page_class = trim(Koken::$page_class . ' k-lens-' . Koken::$location['template']);

				if (!Koken::$public) {
					Koken::$page_class .= ' k-unlisted';
				}

				foreach(Koken::$site['navigation']['items'] as $item)
				{
					if ($item['path'] === $url)
					{
						Koken::$custom_page_title = $item['label'];
						break;
					}
				}

				if (!Koken::$custom_page_title)
				{
					foreach(Koken::$site['navigation']['groups'] as $key => $group)
					{
						foreach($group['items'] as $item)
						{
							if ($item['path'] === $url)
							{
								Koken::$custom_page_title = $item['label'];
								break;
							}
						}
					}
				}
				break;
			}
		}
	}

	if (isset($_COOKIE['share_to_tumblr']) && Koken::$source['type'] === 'content')
	{
		setcookie('share_to_tumblr', "", time() - 3600, '/');
		$final_path = 'content_tumblr_share';
	}

	if (!isset($final_path))
	{
		$default_path = trim($url, '/');
		$test = Koken::get_path("$default_path.lens");
		if ($test)
		{

			$final_path = $default_path;
			Koken::$custom_page_title = str_replace(array('-', '_'), ' ', $final_path);
			Koken::$custom_page_title = function_exists('mb_convert_case') ? mb_convert_case(Koken::$custom_page_title, MB_CASE_TITLE) : ucwords(Koken::$custom_page_title);
			Koken::$location['template'] = str_replace('.', '-', $default_path);

			foreach(Koken::$site['templates'] as $template)
			{
				if ($template['path'] === $default_path)
				{
					Koken::$page_class = 'k-lens-' . Koken::$location['template'];

					if (isset($template['info']['source']))
					{
						Koken::$source = array( 'type' => $template['info']['source'], 'filters' => false );
						Koken::$page_class .= ' k-source-' . $template['info']['source'];
					}
					break;
				}

			}
		}

	}

	if (isset($final_path))
	{
		$protocol = Koken::find_protocol();
		$port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);

		header("X-XHR-Current-Location: " . $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI']);

		if (isset(Koken::$site['settings_flat']))
		{
			foreach(Koken::$site['settings_flat'] as $key => $obj)
			{
				$val = isset($obj['type']) && $obj['type'] === 'boolean' && is_bool($obj['value']) ? (bool) $obj['value'] : $obj['value'];

				if (!$stylesheet && isset($obj['scope']) && !in_array($final_path, $obj['scope']))
				{
					$val = isset($obj['out_of_scope_value']) ? $obj['out_of_scope_value'] : false;
				}
				Koken::$settings[$key] = $val;
			}
		}

		Koken::$settings['language'] = isset(Koken::$settings['language']) ? Koken::$settings['language'] : 'en';
		Koken::$language = Koken::$site['language'][Koken::$settings['language']];

		Koken::$rss = preg_match('/\.rss$/', $final_path);

		$final_path .= '.lens';

		if ($final_path === 'error.lens') {
			$httpErrorCodes = array();
			$httpErrorCodes['400'] = 'Bad Request';
			$httpErrorCodes['401'] = 'Unauthorized';
			$httpErrorCodes['403'] = 'Forbidden';
			$httpErrorCodes['404'] = 'Not Found';
			$httpErrorCodes['405'] = 'Method Not Allowed';
			$httpErrorCodes['406'] = 'Not Acceptable';
			$httpErrorCodes['407'] = 'Proxy Authentication Required';
			$httpErrorCodes['408'] = 'Request Timeout';
			$httpErrorCodes['409'] = 'Conflict';
			$httpErrorCodes['410'] = 'Gone';
			$httpErrorCodes['411'] = 'Length Required';
			$httpErrorCodes['412'] = 'Precondition Failed';
			$httpErrorCodes['413'] = 'Request Entity Too Large';
			$httpErrorCodes['414'] = 'Request-url Too Long';
			$httpErrorCodes['415'] = 'Unsupported Media Type';
			$httpErrorCodes['416'] = 'Requested Range not satisfiable';
			$httpErrorCodes['417'] = 'Expectation Failed';
			$httpErrorCodes['500'] = 'Internal Server Error';
			$httpErrorCodes['501'] = 'Not Implemented';
			$httpErrorCodes['502'] = 'Bad Gateway';
			$httpErrorCodes['503'] = 'Service Unavailable';
			$httpErrorCodes['504'] = 'Gateway Timeout';
			$httpErrorCodes['505'] = 'HTTP Version Not Supported';
			header('HTTP/1.0 ' . $routed_variables['code'] . ' ' . $httpErrorCodes[$routed_variables['code']]);
		}

		$full_path = Koken::get_path($final_path);

		$tmpl = preg_replace( '#<\?.*?(\?>|$)#s', '', file_get_contents($full_path) );

		Koken::$routed_variables = $routed_variables;

		if ($stylesheet)
		{
			function go($tmpl)
			{
				Koken::$settings['style'] =& Koken::$settings['__style'];

				function url($matches)
				{
					$path = $matches[2];

					if ($matches[1])
					{
						$wrap = $matches[1];
					}
					else
					{
						$wrap = '';
					}

					if (strpos($path, 'http') === 0 || strpos($path, 'data:') === 0)
					{
						$path = $path;
					}
					else
					{

						$path = preg_replace('~^../~', '', $path);
						$path = Koken::$location['real_root_folder'] . '/storage/themes/' . Koken::$site['theme']['path'] . "/$path";
					}

					return 'url(' . $wrap . $path . $wrap . ')';
				}

				$raw = preg_replace('/\[?\$([a-z\-_0-9\.]+)\]?/', '<?php echo Koken::get_setting(\'${1}\'); ?>', $tmpl);

				// die($raw);
				$contents = Koken::render($raw);
				$contents = preg_replace_callback('/url\((\'|")?([^\'")]+)(\'|")?\)/', 'url', $contents);

				function to_rgb($matches)
				{
					$color = $matches[1];

					if (strlen($color) === 3)
					{
						$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
					}

					list($r, $g, $b) = array(
											hexdec($color[0].$color[1]),
											hexdec($color[2].$color[3]),
											hexdec($color[4].$color[5])
										);

					return "$r, $g, $b";
				}

				$contents = preg_replace_callback('/to_rgb\(#([0-9a-zA-Z]{3,6})\)/', 'to_rgb', $contents);

				global $final_path;

				if (strpos($final_path, 'lightbox-settings.css.lens') === false)
				{
					$koken_css = file_get_contents(Koken::get_path('common/css/koken.css'));
					$contents = $contents . "\n\n" . $koken_css;
				}

				if (!empty(Koken::$site['custom_css']) && !Koken::$draft)
				{
					$contents .= "\n\n" . Koken::$site['custom_css'];
				}

				Koken::cache($contents);

				header('Content-type: text/css');
				die($contents);
			}
		}
		else
		{
			// For autoloading tagName classes as needed
			function koken_site_autoloader($class_name)
			{
				include "tags/$class_name.php";
			}

			spl_autoload_register('koken_site_autoloader');

			function parse_replacements($matches)
			{
				if (isset(Koken::$settings[$matches[2]]))
				{
					return Koken::$settings[$matches[2]];
				}
				else
				{
					return Koken::$settings['__scoped_' . str_replace('.', '-', Koken::$location['template']) . '_' . $matches[2]];
				}
			}

			function parse_include($matches)
			{
				$path = preg_replace_callback('/\{\{\s*(site\.)?settings\.([^\}\s]+)\s*\}\}/', 'parse_replacements', $matches[1]);
				$path = Koken::get_path($path);
				if ($path)
				{
					return file_get_contents($path);
				}
				return '';
			}

			function parse_asset($matches)
			{
				$id = '';
				$passthrough = array();
				$if = false;

				if ($matches[1] === 'settings')
				{
					global $final_path;
					$file = 'settings.css.lens';
					if ($final_path === 'lightbox.lens')
					{
						if (!file_exists(Koken::$template_path . '/css/lightbox-settings.css.lens'))
						{
							return '';
						}

						$file = 'lightbox-' . $file;
					}
					$path = Koken::$location['root_folder'] . '/' . (Koken::$draft ? 'preview.php?/' : (Koken::$rewrite ? '' : 'index.php?/')) . $file . (Koken::$preview ? '&preview=' . Koken::$preview : '');
					$info = array( 'extension' => 'css' );
					$id = ' id="koken_settings_css_link"';
				}
				else
				{
					preg_match_all('/([a-z_]+)="([^"]+)"/', $matches[1], $params);

					foreach($params[1] as $i => $name)
					{
						$value = $params[2][$i];

						if ($name === 'file')
						{
							$file = $value;
						}
						else if ($name === 'common')
						{
							$common = $value;
						}
						else if ($name === 'if')
						{
							$if = str_replace('settings.', '', $value);
						}
						else if ($name === 'version')
						{
							$version = $value;
						}
						else
						{
							$passthrough[] = "$name=\"$value\"";
						}
					}

					$info = pathinfo($file);

					if (strpos($file, 'http') === 0)
					{
						$path = $file;
					}
					else
					{
						if (isset($common) && $common)
						{
							$path = '/app/site/themes/common/' . $info['extension'] . '/' . $file;
							$buster = KOKEN_VERSION;

							if (!file_exists(Koken::$root_path . $path))
							{
								return '';
							}

							$path = Koken::$location['real_root_folder'] . $path . '?' . $buster;
						}
						else
						{
							$path = Koken::get_path($file, true);

							if (!$path)
							{
								return '';
							}

							if (isset($version) && $version !== 'false')
							{
								if ($version === 'true')
								{
									$buster = Koken::$site['theme']['version'];
								}
								else
								{
									$buster = $version;
								}

								$path .= '?' . $buster;
							}
						}
					}
				}

				if (count($passthrough))
				{
					$parameters = ' ' . join(' ', $passthrough);
				}
				else
				{
					$parameters = '';
				}

				if ($if && !Koken::$settings[$if])
				{
					return '';
				}

				if (!isset($info['extension']))
				{
					$info['extension'] = strpos($path, 'css') === false ? 'js' : 'css';
				}

				if ($info['extension'] == 'css' || $info['extension'] == 'less')
				{
					return "<link$id rel=\"stylesheet\" type=\"text/{$info['extension']}\" href=\"$path\"$parameters />";
				}
				else if ($info['extension'] == 'js')
				{
					return "<script src=\"$path\"$parameters></script>";
				}
				else if (in_array($info['extension'], array('jpeg', 'jpg', 'gif', 'png')))
				{
					return "<img src=\"$path\"$parameters />";
				}
				else if ($info['extension'] === 'svg')
				{
					return "<embed src=\"$path\" type=\"image/svg+xml\"$parameters />";
				}
			}

			while (strpos($tmpl, '<koken:include') !== false)
			{
				$tmpl = preg_replace_callback('/<koken\:include\sfile="([^"]+?)" \/>/', 'parse_include', $tmpl);
			}

			$tmpl = preg_replace_callback('/<koken\:asset\s?(.+?)\s?\/>/', 'parse_asset', $tmpl);
			$tmpl = preg_replace_callback('/<koken\:(settings)\s?\/>/', 'parse_asset', $tmpl);

			// Wrap this to control context, variable availability
			function go($tmpl, $pass = 1)
			{
				$raw = Koken::parse($tmpl);

				// Fix PHP whitespace issues in koken:loops
				$raw = preg_replace('/\s+<\?php\s+endforeach/', '<?php endforeach', $raw);
				$raw = preg_replace('/<a(.*)>\s+<\?php/', '<a$1><?php', $raw);
				$raw = preg_replace('/\?>\s+<\/a>/', '?></a>', $raw);

				if ($pass === 1)
				{
					global $final_path;
					$is_lightbox = 'false';
					if ($final_path === 'lightbox.lens')
					{
						$is_lightbox = 'true';
					}

					// Filters
					$raw = str_replace('<head>', "<head><?php Shutter::hook('after_opening_head', array(array('lightbox' => $is_lightbox))); ?>", $raw);
					$raw = str_replace('</head>', "<?php Shutter::hook('before_closing_head', array(array('lightbox' => $is_lightbox))); ?></head>", $raw);
					$raw = str_replace('<body>', "<body><?php Shutter::hook('after_opening_body', array(array('lightbox' => $is_lightbox))); ?>", $raw);
					$raw = str_replace('</body>', "<?php Shutter::hook('before_closing_body', array(array('lightbox' => $is_lightbox))); ?></body>", $raw);

					if (Koken::$pjax)
					{
						$raw = "<?php Shutter::hook('before_pjax', array(array('lightbox' => $is_lightbox))); ?>" . $raw;
						$raw .= "<?php Shutter::hook('after_pjax', array(array('lightbox' => $is_lightbox))); ?>";
					}

					// die($raw);
					Koken::$location['page_class'] = Koken::$page_class;
					$dynamic_array = array();
					foreach(Koken::$dynamic_location_parts as $key) {
						$dynamic_array[$key] = Koken::$location[$key];
					}

					unset($dynamic_array['parameters']['__overrides']);
					unset($dynamic_array['parameters']['__overrides_display']);

					$location_json = json_encode($dynamic_array);

					if (Koken::$pjax)
					{
						$js = "<script>\$K.location = $.extend(\$K.location, $location_json);$(window).trigger('k-pjax-end');</script>";
					}
					else
					{
						$location = Koken::$location;
						$site = Koken::$site;

						$stamp = '?' . KOKEN_VERSION;
						$generator = 'Koken ' . KOKEN_VERSION;
						$theme = Koken::$site['theme']['name'] . ' ' . Koken::$site['theme']['version'];

						$koken_js = Koken::$location['root_folder'] . '/' . (Koken::$draft ? 'preview.php?/' : (Koken::$rewrite ? '' : 'index.php?/')) . 'koken.js' . (Koken::$preview ? '&preview=' . Koken::$preview : '');
						if (strpos($koken_js, '.php?') === false)
						{
							$koken_js .= '?' . Shutter::get_site_scripts_timestamp();
						}

						if (Koken::$has_video)
						{
							$me = "\n\n\t<link href=\"{$location['real_root_folder']}/app/site/themes/common/css/mediaelement/mediaelementplayer.css{$stamp}\" rel=\"stylesheet\">\n";
						}
						else
						{
							$me = '';
						}

						$js = <<<JS
	<meta name="generator" content="$generator" />
	<meta name="theme" content="$theme" />$me

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	<script>window.jQuery || document.write('<script src="{$location['real_root_folder']}/app/site/themes/common/js/jquery.min.js"><\/script>')</script>
	<script src="{$koken_js}"></script>
	<script>\$K.location = $.extend(\$K.location, $location_json);</script>

	<link rel="alternate" type="application/atom+xml" title="{$site['title']}: All uploads" href="{$location['root']}/feed/content/recent.rss" />
	<link rel="alternate" type="application/atom+xml" title="{$site['title']}: Essays" href="{$location['root']}/feed/essays/recent.rss" />
	<link rel="alternate" type="application/atom+xml" title="{$site['title']}: Timeline" href="{$location['root']}/feed/timeline/recent.rss" />
JS;
					}

					if (Koken::$draft && !Koken::$preview && !Koken::$pjax)
					{
						$original_url = Koken::$original_url;
						$js .= <<<JS
<script>

if (parent && parent.\$) {
	parent.\$(parent.document).trigger('previewready', '$original_url');
	$(function() { parent.\$(parent.document).trigger('previewdomready'); });

	$(document).on('pjax:end pjax:transition:end', function(event) {
		if (event.type === 'pjax:end') {
			parent.\$(parent.document).trigger('previewready', location.href);
		}
		parent.\$(parent.document).trigger('previewdomready');
	});

	$(document).on('page:change.console', function() {
		parent.\$(parent.document).trigger('previewready', location.href);
		parent.\$(parent.document).trigger('previewdomready');
	});
}
if (parent && parent.__koken__) {
	\$(window).on('keydown', function(e) { parent.__koken__.shortcuts(e); });
	\$(function() { parent.__koken__.panel(); });
}
</script>

<style type="text/css">
i.k-control-structure { font-style: normal !important; }

	div[data-pulse-group] div.cover {
		width: 100%;
		height: 100%;
		z-index: 1000;
		border: 5px solid transparent;
		box-sizing: border-box;
		position: absolute;
		box-shadow: 0 0 20px rgba(0,0,0,0.6);
		display: none;
		pointer-events:none;
		top: 0;
		left: 0;
	}

	div[data-pulse-group]:hover div.cover, div[data-pulse-group] div.cover.active {
		display: block !important;
	}

	div[data-pulse-group] div.cover.active {
		border-color: #ff6e00 !important;
	}

	div[data-pulse-group] div.cover div {
		pointer-events:auto;
		width: 10%;
		height: 10%;
		min-width: 28px;
		min-height: 28px;
		background-size: 28px 28px;
		background-position:top right;
		background-repeat:no-repeat;
		background-image: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxOC4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiDQoJIHZpZXdCb3g9IjAgMCAyOCAyOCIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMjggMjgiIHhtbDpzcGFjZT0icHJlc2VydmUiPg0KPGcgaWQ9IkxheWVyXzIiPg0KCTxjaXJjbGUgZmlsbD0iIzFFMUUxRSIgY3g9IjE0IiBjeT0iMTQiIHI9IjE0Ii8+DQo8L2c+DQo8ZyBpZD0iTGF5ZXJfMSI+DQoJPGcgaWQ9ImNvZ18xXyI+DQoJCTxwYXRoIGZpbGw9IiNCQkJCQkIiIGQ9Ik0xNCwyMC41Yy0wLjMsMC0wLjYsMC0wLjgtMC4xbC0wLjQtMS43bC0wLjEsMGMtMC40LTAuMS0wLjctMC4zLTEuMS0wLjRsLTAuMSwwTDEwLDE5LjENCgkJCWMtMC41LTAuNC0wLjktMC44LTEuMi0xLjJsMC45LTEuNWwwLTAuMWMtMC4yLTAuMy0wLjMtMC43LTAuNC0xLjFsMC0wLjFsLTEuNy0wLjRjMC0wLjMtMC4xLTAuNi0wLjEtMC44YzAtMC4zLDAtMC42LDAuMS0wLjgNCgkJCWwxLjctMC40bDAtMC4xYzAuMS0wLjQsMC4yLTAuNywwLjQtMS4xbDAtMC4xTDguOSwxMEM5LjIsOS42LDkuNiw5LjIsMTAsOC45bDEuNSwwLjlsMC4xLDBjMC4zLTAuMiwwLjctMC4zLDEuMS0wLjRsMC4xLDANCgkJCWwwLjQtMS43YzAuMywwLDAuNi0wLjEsMC44LTAuMWMwLjMsMCwwLjYsMCwwLjgsMC4xbDAuNCwxLjdsMC4xLDBjMC40LDAuMSwwLjcsMC4yLDEuMSwwLjRsMC4xLDBMMTgsOC45YzAuNSwwLjQsMC45LDAuOCwxLjIsMS4yDQoJCQlsLTAuOSwxLjVsMCwwLjFjMC4yLDAuMywwLjMsMC43LDAuNCwxLjFsMCwwLjFsMS43LDAuNGMwLDAuMywwLjEsMC42LDAuMSwwLjhjMCwwLjMsMCwwLjYtMC4xLDAuOGwtMS43LDAuNGwwLDAuMQ0KCQkJYy0wLjEsMC40LTAuMywwLjctMC40LDEuMWwwLDAuMWwwLjksMS41Yy0wLjQsMC41LTAuOCwwLjktMS4yLDEuMmwtMS41LTAuOWwtMC4xLDBjLTAuMywwLjItMC43LDAuMy0xLjEsMC40bC0wLjEsMGwtMC40LDEuNw0KCQkJQzE0LjUsMjAuNSwxNC4zLDIwLjUsMTQsMjAuNXogTTE0LDExLjZjLTEuMywwLTIuNCwxLjEtMi40LDIuNGMwLDEuMywxLjEsMi40LDIuNCwyLjRjMS4zLDAsMi40LTEuMSwyLjQtMi40DQoJCQlDMTYuNCwxMi43LDE1LjMsMTEuNiwxNCwxMS42eiIvPg0KCTwvZz4NCjwvZz4NCjwvc3ZnPg0K);		position: absolute;
		top: 4px;
		right: 4px;
		cursor: pointer;
		z-index: 1001;
	}

	div[data-pulse-group] div.cover div:hover {
		background-image: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxOC4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiDQoJIHZpZXdCb3g9IjAgMCAyOCAyOCIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMjggMjgiIHhtbDpzcGFjZT0icHJlc2VydmUiPg0KPGcgaWQ9IkxheWVyXzIiPg0KCTxjaXJjbGUgZmlsbD0iIzFFMUUxRSIgY3g9IjE0IiBjeT0iMTQiIHI9IjE0Ii8+DQo8L2c+DQo8ZyBpZD0iTGF5ZXJfMSI+DQoJPGcgaWQ9ImNvZ18yXyI+DQoJCTxwYXRoIGZpbGw9IiNFRUVFRUUiIGQ9Ik0xNCwyMC41Yy0wLjMsMC0wLjYsMC0wLjgtMC4xbC0wLjQtMS43bC0wLjEsMGMtMC40LTAuMS0wLjctMC4zLTEuMS0wLjRsLTAuMSwwTDEwLDE5LjENCgkJCWMtMC41LTAuNC0wLjktMC44LTEuMi0xLjJsMC45LTEuNWwwLTAuMWMtMC4yLTAuMy0wLjMtMC43LTAuNC0xLjFsMC0wLjFsLTEuNy0wLjRjMC0wLjMtMC4xLTAuNi0wLjEtMC44YzAtMC4zLDAtMC42LDAuMS0wLjgNCgkJCWwxLjctMC40bDAtMC4xYzAuMS0wLjQsMC4yLTAuNywwLjQtMS4xbDAtMC4xTDguOSwxMEM5LjIsOS42LDkuNiw5LjIsMTAsOC45bDEuNSwwLjlsMC4xLDBjMC4zLTAuMiwwLjctMC4zLDEuMS0wLjRsMC4xLDANCgkJCWwwLjQtMS43YzAuMywwLDAuNi0wLjEsMC44LTAuMWMwLjMsMCwwLjYsMCwwLjgsMC4xbDAuNCwxLjdsMC4xLDBjMC40LDAuMSwwLjcsMC4yLDEuMSwwLjRsMC4xLDBMMTgsOC45YzAuNSwwLjQsMC45LDAuOCwxLjIsMS4yDQoJCQlsLTAuOSwxLjVsMCwwLjFjMC4yLDAuMywwLjMsMC43LDAuNCwxLjFsMCwwLjFsMS43LDAuNGMwLDAuMywwLjEsMC42LDAuMSwwLjhjMCwwLjMsMCwwLjYtMC4xLDAuOGwtMS43LDAuNGwwLDAuMQ0KCQkJYy0wLjEsMC40LTAuMywwLjctMC40LDEuMWwwLDAuMWwwLjksMS41Yy0wLjQsMC41LTAuOCwwLjktMS4yLDEuMmwtMS41LTAuOWwtMC4xLDBjLTAuMywwLjItMC43LDAuMy0xLjEsMC40bC0wLjEsMGwtMC40LDEuNw0KCQkJQzE0LjUsMjAuNSwxNC4zLDIwLjUsMTQsMjAuNXogTTE0LDExLjZjLTEuMywwLTIuNCwxLjEtMi40LDIuNGMwLDEuMywxLjEsMi40LDIuNCwyLjRjMS4zLDAsMi40LTEuMSwyLjQtMi40DQoJCQlDMTYuNCwxMi43LDE1LjMsMTEuNiwxNCwxMS42eiIvPg0KCTwvZz4NCjwvZz4NCjwvc3ZnPg0K);
	}
</style>
JS;
					}
				}

				$contents = Koken::render($raw);

				if ($pass === 1)
				{
					// Rerun parse to catch shortcode renders
					while(strpos($contents, '<koken:') !== false && $pass < 3)
					{
						$pass++;
						$contents = go($contents, $pass);
					}
				}
				else
				{
					return $contents;
				}

				$contents .= Koken::cleanup();

				if ((strpos($contents, 'settings.css.lens"') === false && !empty(Koken::$site['custom_css'])) || Koken::$draft)
				{
					$js .= '<style id="koken_custom_css">' . Koken::$site['custom_css'] . '</style>';
				}

				preg_match_all('/<\!\-\- KOKEN HEAD BEGIN \-\->(.*)<!\-\- KOKEN HEAD END \-\->/msU', $contents, $headers);
				$contents = preg_replace('/<\!\-\- KOKEN HEAD BEGIN \-\->(.*)<!\-\- KOKEN HEAD END \-\->/msU', '', $contents);

				$header_str = '';

				foreach($headers[1] as $header)
				{
					$header_str .= "\t" . $header . "\n";
				}

				if (strpos($header_str, '<title>') !== false)
				{
					$contents = preg_replace('/<title>.*<\/title>/msU', '', $contents);
					$header_str = preg_replace('/<koken_title>.*<\/koken_title>/', '', $header_str);
				}
				else if (strpos($header_str, '<koken_title>') !== false && strpos($contents, '<koken_title') !== false)
				{
					$contents = preg_replace('/<title>.*<\/title>/msU', '', $contents);
					$header_str = str_replace('koken_title', 'title', $header_str);
				}
				else if (strpos($contents, '<koken_title') !== false)
				{
					$contents = str_replace('koken_title', 'title', $contents);
				}

				if (Koken::$pjax && strpos($header_str, '<title>'))
				{
					preg_match('~<title>.*</title>~', $header_str, $title_match);
					$contents .= $title_match[0];
				}

				$contents = preg_replace('/<koken_title>.*<\/koken_title>/msU', '', $contents);

				$header_str .= "\n\t<!--[if IE]>\n\t<script src=\"" . Koken::$location['real_root_folder'] . "/app/site/themes/common/js/html5shiv.js\"></script>\n\t<![endif]-->\n";

				if (strpos($contents, '<head>'))
				{
					preg_match('/<head>(.*)?<\/head>/msU', $contents, $header);
					if (count($header))
					{
						$head = isset($header[1]) ? $header[1] : '';
						preg_match_all('/<script.*<\/script>/msU', $head, $head_js);
						$head = preg_replace('/\s*<script.*<\/script>\s*/msU', '', $head) . "\n$header_str\n$js\n" . join("\n", $head_js[0]);
						$contents = preg_replace('/<head>(.*)?<\/head>/msU', "<head>\n" . str_replace('$', '\$', $head) . "\n</head>", $contents);
					}

				}
				else if (strpos($contents, '</body>'))
				{
					$contents = str_replace('</body>', "$js\n$header_str\n</body>", $contents);
				}
				else if (Koken::$pjax)
				{
					$contents .= $js;
				}

				$final_page_classes = trim(join(' ', array_merge(explode(' ', Koken::$page_class), Shutter::get_body_classes())));

				if (preg_match_all('/<body(?:[^>]+)?>/', $contents, $match) && !empty($final_page_classes)) {
					foreach($match[0] as $body)
					{
						if (strpos($body, 'class="') !== false)
						{
							$new_body = preg_replace('/class="([^"]+)"/', "class=\"$1 " . $final_page_classes . "\"", $body); }
						else
						{
							$new_body = str_replace('>', ' class="' . $final_page_classes . '">', $body);
						}
						$contents = str_replace($body, $new_body, $contents);
					}
				}

				if (preg_match_all('/<html(?:[^>]+)?>/', $contents, $match) && !empty($final_page_classes)) {
					foreach($match[0] as $html)
					{
						if (strpos($html, 'class="') !== false)
						{
							$new_html = preg_replace('/class="([^"]+)"/', "class=\"$1 " . $final_page_classes . "\"", $html); }
						else
						{
							$new_html = str_replace('>', ' class="' . $final_page_classes . '">', $html); }
						$contents = str_replace($html, $new_html, $contents);
					}
				}

				preg_match('/<!-- KOKEN META DESCRIPTION BEGIN -->(.*)<!-- KOKEN META DESCRIPTION END -->/msU', $contents, $meta_description);
				preg_match('/<!-- KOKEN META KEYWORDS BEGIN -->(.*)<!-- KOKEN META KEYWORDS END -->/msU', $contents, $meta_keywords);

				$contents = preg_replace('/<!-- KOKEN META (DESCRIPTION|KEYWORDS) BEGIN -->.*<!-- KOKEN META (DESCRIPTION|KEYWORDS) END -->/msU', '', $contents);

				$contents = preg_replace('/\t+/', "\t", $contents);
				$contents = preg_replace('/\n\t*\n/', "\n", $contents);
				$contents = preg_replace('/\n{2,}/', "\n\n", $contents);
				$contents = preg_replace('/<title>\s*/ms', '<title>', $contents);


				if (count($meta_description) && strlen($meta_description[1]) > 0)
				{
					$contents = preg_replace('/<meta name="description" content=".*" \/>/', '<meta name="description" content="' . str_replace('$', '\$', $meta_description[1]) . '" />', $contents);
				}

				if (count($meta_keywords) && strlen($meta_keywords[1]) > 0)
				{
					$contents = preg_replace('/<meta name="keywords" content="(.*)" \/>/', "<meta name=\"keywords\" content=\"$1, {$meta_keywords[1]}\" />", $contents);
				}

				if (Koken::$rss)
				{
					$contents = '<?xml version="1.0" encoding="utf-8"?>' . "\n$contents";
				}
				else
				{
					$contents = Shutter::filter('site.output', $contents);
				}

				Koken::cache($contents);

				if (Koken::$rss)
				{
					header('Content-type: text/xml; charset=UTF-8');
				}
				else
				{
					header('Content-type: text/html; charset=UTF-8');
				}

				die($contents);

			}
		}

		go($tmpl);
	} else {
		if ($http_error)
		{
			header('HTTP/1.0 404 Not Found');
		}
		else
		{
			header("Location: $base_path/error/404/");
		}
	}
