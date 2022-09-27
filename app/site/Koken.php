<?php

define('ENVIRONMENT', 'production');

require_once('lib/autolink.php');

class Koken
{
    public static $site;
    public static $settings = [];
    public static $language = [];
    public static $profile;
    public static $location = [];
    public static $template_routes = [];
    public static $rss_feeds = [];
    public static $current_token;
    public static $rss;
    public static $categories;
    public static $messages;
    public static $page_class = false;
    public static $template_path;
    public static $fallback_path;
    public static $navigation_home_path = false;
    public static $original_url;
    public static $cache_path;
    public static $routed_variables;
    public static $draft;
    public static $preview;
    public static $rewrite;
    public static $pjax;
    public static $source;
    public static $template_variable_keys = [];
    public static $template_variables = [];
    public static $root_path;
    public static $protocol;
    public static $main_load_token = false;
    public static $custom_page_title = false;
    public static $tokens = [];
    public static $max_neighbors = array(2);
    public static $the_title_separator = false;
    public static $page_title_set = false;
    public static $load_history = [];
    public static $timers = [];
    public static $curl_handle = false;
    public static $dynamic_location_parts = array('here', 'parameters', 'page_class');
    public static $has_video = false;
    public static $link_tail = '';
    public static $public = true;

    private static $start_time = false;
    private static $last;
    private static $_parent = false;
    private static $level = 0;
    private static $depth;

    public static function meta()
    {
        $site = self::$site;
        return <<<META

\t<meta name="description" content="{$site['description']}" />
\t<meta name="author" content="{$site['profile']['name']}" />
\t<meta name="keywords" content="{$site['keywords']}" />

META;
    }

    public static function get_setting($name)
    {
        if (strpos($name, '.') !== false) {
            $parts = explode('.', $name);
            if ($parts[0] === 'language') {
                return Koken::$language[$parts[1]];
            } else {
                return Koken::$settings['__scoped_' . str_replace('.', '-', $parts[1]) . '_' . $parts[0]];
            }
        } else {
            return Koken::$settings[$name];
        }
    }
    public static function title_from_archive($archive, $format = false)
    {
        if (!$archive['month']) {
            return $archive['year'];
        }

        $str = $archive['year'] . '-' . $archive['month'] . '-01 12:00:00';

        if (isset($archive['day']) && $archive['day']) {
            if (!$format) {
                $format = 'F j, Y';
            }
            $str = str_replace('-01 ', '-' . $archive['day'] . ' ', $str);
        } else {
            if (!$format) {
                $format = 'F Y';
            }
        }

        if (class_exists('IntlDateFormatter') && isset(self::$settings['language']) && self::$settings['language'] !== 'en') {
            $df = new IntlDateFormatter(self::$settings['language'], IntlDateFormatter::NONE, IntlDateFormatter::NONE);
            $df->setPattern(self::to_date_field_symbol($format));

            $archive = $df->format(strtotime($str));
        } else {
            $archive = date($format, strtotime($str));
        }

        return $archive;
    }

    private static function to_month($m)
    {
        return date('F', strtotime("2012-$m-01"));
    }

    private static function to_date_field_symbol($f)
    {
        $dfs = '';
        $chars = str_split($f);

        foreach ($chars as $c) {
            switch ($c) {
                case 'd':
                    $dfs .= 'dd';
                    break;
                case 'D':
                    $dfs .= 'EEE';
                    break;
                case 'j':
                    $dfs .= 'd';
                    break;
                case 'l':
                    $dfs .= 'EEEE';
                    break;
                case 'F':
                    $dfs .= 'MMMM';
                    break;
                case 'm':
                    $dfs .= 'MM';
                    break;
                case 'M':
                    $dfs .= 'MMM';
                    break;
                case 'n':
                    $dfs .= 'M';
                    break;
                case 'Y':
                    $dfs .= 'YYYY';
                    break;
                case 'y':
                    $dfs .= 'YY';
                    break;
                case 'A':
                    $dfs .= 'a';
                    break;
                case 'g':
                    $dfs .= 'h';
                    break;
                case 'G':
                    $dfs .= 'H';
                    break;
                case 'h':
                    $dfs .= 'hh';
                    break;
                case 'H':
                    $dfs .= 'HH';
                    break;
                case 'i':
                    $dfs .= 'mm';
                    break;
                case 's':
                    $dfs .= 'ss';
                    break;
                case 'S':
                    break;
                default:
                    $dfs .= $c;
            }
        }

        return $dfs;
    }

    public static function breadcrumbs($options = array())
    {
        $front_path = '/';

        foreach (self::$site['navigation']['items'] as $item) {
            if ($item['front']) {
                $front_path = $item['path'];
                break;
            }
        }

        $defaults = array('separator' => '>', 'show_if_single' => 'true', 'show_home' => 'true', 'link_current' => 'true');

        $options = array_merge($defaults, $options);

        $options['show_if_single'] = $options['show_if_single'] === 'true';
        $options['show_home'] = $options['show_home'] === 'true';
        $options['link_current'] = $options['link_current'] === 'true';

        $options['separator'] = ' ' . $options['separator'] . ' ';

        $base = Koken::$location['root'];
        $link_tail = Koken::$preview ? '&amp;preview=' . Koken::$preview : '';

        $crumbs = [];

        if ($options['show_home']) {
            $crumbs[] =	array('link' => '/', 'label' => self::$site['url_data']['home']);
        }

        if (isset(self::$current_token['__koken__']) && self::$current_token['__koken__'] !== 'category') {
            $single = self::$current_token;
        } elseif (isset(self::$current_token['album'])) {
            $single = self::$current_token['album'];
        } elseif (isset(self::$current_token['event']) && isset(self::$current_token['event']['counts'])) {
            $single = self::$current_token['event'];
        } else {
            $single = false;
        }

        $source = self::$source['type'];

        if (isset($single['context']['type'])) {
            if (isset(self::$site['urls'][$single['context']['type'] === 'category' ? 'categories' : $single['context']['type'] . 's'])) {
                $section = self::$site['urls'][$single['context']['type'] === 'category' ? 'categories' : $single['context']['type'] . 's'];
                $crumbs[] = array('link' => $section, 'label' => self::$site['url_data'][ $single['context']['type'] ]['plural']);
            }
            if ($single['context']['type'] === 'album') {
                $section = str_replace(':slug', $single['context']['album']['slug'], self::$site['urls'][$single['context']['type']]);
                $crumbs[] = array('link' => $single['context']['album']['__koken_url'], 'label' => $single['context']['title']);
            } elseif ($single['context']['type'] !== 'favorite' && $single['context']['type'] !== 'feature' && isset(self::$site['urls'][$single['context']['type']])) {
                $section = str_replace(':slug', $single['context']['slug'], self::$site['urls'][$single['context']['type']]);
                $crumbs[] = array('link' => $section, 'label' => $single['context']['title']);
            }
            if (!in_array($single['context']['type'], array('favorite', 'feature', 'album'))) {
                $crumbs[] = array('link' => $single['context']['__koken_url'], 'label' => self::$site['url_data'][$single['__koken__']]['plural']);
            }
            $crumbs[] = array('link' => $single['__koken_url'], 'label' => empty($single['title']) ? $single['filename'] : $single['title']);
            $single = false;
        } elseif (isset($single['context']['album'])) {
            $content = $single;
            $single = $single['context']['album'];
        } else {
            $content = false;
        }

        if ($single && $source === 'event') {
            $data = self::$site['url_data']['timeline'];
            $url = self::$site['urls']['timeline'];
            $single['month'] = str_pad($single['month'], 2, '0', STR_PAD_LEFT);
            $single['day'] = str_pad($single['day'], 2, '0', STR_PAD_LEFT);

            $crumbs[] = array('link' => $url, 'label' => $data['plural']);
            $url .= $single['year'] . '/';
            $crumbs[] = array('link' => $url, 'label' => $single['year']);
            $url .= $single['month'] . '/';
            $crumbs[] = array('link' => $url, 'label' => self::to_month($single['month']));
            $url .= $single['day'] . '/';
            $crumbs[] = array('link' => $url, 'label' => $single['day']);
        } elseif ($single) {
            $set = $single['__koken__'] === 'album' && $single['album_type'] === 'set';
            $ident = $set ? 'set' : $single['__koken__'];
            $data = self::$site['url_data'][$ident];
            $plural = $ident === 'category' ? 'categories' : $ident . 's';
            if ($set) {
                $data['url'] = self::$site['url_data']['album']['url'];
            }

            if (isset(self::$site['urls'][$plural])) {
                $section = self::$site['urls'][$plural];
                $crumbs[] = array('link' => $section, 'label' => self::$site['url_data'][ $set ? 'set' : $single['__koken__'] ]['plural']);
            }

            if (isset($data['url']) && strpos($data['url'], 'date') !== false && isset(self::$site['urls'][ 'archive_' . $single['__koken__'] . 's' ])) {
                $date = isset($single['published_on']) ? $single['published_on'] : ($single['captured_on'] ? $single['captured_on'] : $single['created_on']);
                $year = date('Y', $date['timestamp']);
                $month = date('m', $date['timestamp']);

                $crumbs[] = array('link' => $section . $year, 'label' => $year);
                $crumbs[] = array('link' => $section . $year . '/' . $month, 'label' => self::to_month($month));
            }

            $crumbs[] = array('link' => $single['__koken_url'], 'label' => empty($single['title']) ? $single['filename'] : $single['title']);

            if ($content) {
                $crumbs[] = array('link' => $content['__koken_url'], 'label' => empty($content['title']) ? $content['filename'] : $content['title']);
            }
        } elseif (substr(strrev($source), 0, 1) === 's') {
            $data_type = $source === 'categories' ? 'category' : rtrim($source, 's');
            $data_type = $data_type === 'event' ? 'timeline' : $data_type;
            $data = self::$site['url_data'][$data_type];
            $url = self::$site['urls'][ $data_type === 'timeline' ? 'timeline' : $source ];
            $crumbs[] = array('link' => $url, 'label' => $data['plural']);

            if ($data_type === 'timeline' && isset(self::$routed_variables['year'])) {
                $url .= self::$routed_variables['year'] . '/';
                $crumbs[] = array('link' => $url, 'label' => self::$routed_variables['year']);

                if (isset(self::$routed_variables['month'])) {
                    $crumbs[] = array('link' => $url . self::$routed_variables['month'] . '/', 'label' => self::to_month(self::$routed_variables['month']));
                }
            }
        } elseif ($source === 'tag' || $source === 'category' || $source === 'archive') {
            $type = str_replace('members=', '', self::$source['filters'][0]);
            $section = empty($type) ? false : self::$site['urls'][ $type ];

            if ($source === 'archive') {
                $crumbs[] = array('link' => '/' . $section, 'label' => self::$site['url_data'][ rtrim($type, 's') ]['plural']);

                $year = self::$current_token['archive']['year'];
                $url = $section . $year . '/';
                $crumbs[] = array('link' => $url, 'label' => $year);

                if (isset(self::$current_token['archive']['month']) && self::$current_token['archive']['month']) {
                    $month = self::$current_token['archive']['month'];
                    $url .= $month . '/';
                    $crumbs[] = array('link' => $url, 'label' => self::to_month($month));

                    if (isset(self::$current_token['archive']['day']) && self::$current_token['archive']['day']) {
                        $day = self::$current_token['archive']['day'];
                        $url .= $day . '/';
                        $crumbs[] = array('link' => $url, 'label' => $day);
                    }
                }
            } else {
                $section = false;
                if (isset(self::$site['urls'][$source === 'category' ? 'categories' : $source . 's'])) {
                    $section = self::$site['urls'][$source === 'category' ? 'categories' : $source . 's'];
                    $crumbs[] = array('link' => $section, 'label' => self::$site['url_data'][ $source ]['plural']);
                }
                if (isset(self::$site['urls'][$source])) {
                    $obj = isset(self::$current_token['__koken__']) ? self::$current_token[self::$current_token['__koken__']] : self::$current_token['archive'];
                    $section = str_replace(':slug', isset($obj['slug']) ? $obj['slug'] : $obj['title'], self::$site['urls'][$source]);
                    $crumbs[] = array('link' => $section, 'label' => $obj['title']);
                }
                if (!empty($type)) {
                    $data = self::$site['url_data'][ rtrim($type, 's') ];
                    $obj = isset(self::$current_token['__koken__']) ? self::$current_token[self::$current_token['__koken__']] : self::$current_token['archive'];
                    if ($section) {
                        $crumbs[] = array('link' => self::$location['here'], 'label' => $data['plural']);
                    } else {
                        $crumbs[] = array('link' => '/' . strtolower($data['plural']) . '/', 'label' => $data['plural']);
                        $crumbs[] = array('link' => self::$location['here'], 'label' => $obj['title']);
                    }
                }
            }
        } elseif (self::$custom_page_title) {
            $crumbs[] = array('link' => self::$location['here'], 'label' => self::$custom_page_title);
        }

        if (count(self::$location['parameters']['__overrides_display'])) {
            foreach (self::$location['parameters']['__overrides_display'] as $filter) {
                $crumbs[] = array('link' => self::$location['here'], 'label' => self::case_convert($filter['title'], 'sentence') . ': ' . ($filter['title'] === 'tags' ? $filter['value'] : self::case_convert($filter['value'], 'sentence')));
            }
        }

        if (!$options['show_if_single'] && count($crumbs) < 2) {
            return '';
        } else {
            $crumb_links = [];
            foreach ($crumbs as $index => $c) {
                $path = $c['link'] === '/' ? '/' : '/' . trim($c['link'], '/') . '/';
                if ($path === $front_path) {
                    $path = '/';
                }
                if ($index + 1 === count($crumbs) && !$options['link_current']) {
                    $crumb_links[] = $c['label'];
                } else {
                    $crumb_links[] = '<a title="' . $c['label'] . '" href="' . $base . $path . $link_tail . '" data-koken-internal>' . $c['label'] . '</a>';
                }
            }
            return '<span class="k-nav-breadcrumbs">' . join($options['separator'], $crumb_links) . '</span>';
        }
    }

    private static function out_callback($matches)
    {
        return '<?php echo Koken::out(\'' . trim(str_replace("'", "\\'", $matches[1])) . '\'); ?>';
    }

    public static function parse($template)
    {
        $output = preg_replace_callback('/(<|\s)(\/)?koken\:([a-z_\-]+)([\=|\s][^\/].+?")?(\s*\/)?>/', array('Koken', 'callback'), $template);
        $output = preg_replace('/\{\{\s*discussion\s*\}\}/', '<?php Shutter::hook(\'discussion\', array(Koken::$current_token)); ?>', $output);
        $output = preg_replace('/\{\{\s*discussion_count\s*\}\}/', '<?php Shutter::hook(\'discussion_count\', array(Koken::$current_token)); ?>', $output);
        $output = preg_replace('/\{\{\s*rating\s*\}\}/', '<?php Shutter::hook(\'rating\', array(Koken::$current_token)); ?>', $output);
        $output = preg_replace_callback('/\{\{\s*([^\}]+)\s*\}\}/', array('Koken', 'out_callback'), $output);
        return $output;
    }

    public static function find_protocol()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http';

        return $protocol;
    }

    private static function attr_callback($matches)
    {
        $name = $matches[1];

        $value = preg_replace_callback("/{([a-z._\(\)\,\|\s\'\/\[\]0-9]+)([\s\*\-\+0-9]+)?}/", array('Koken', 'attr_replace'), $matches[2]);
        $value = trim($value, '" . ');
        $value = str_replace('"str_replace(', 'str_replace(', $value);

        if (!preg_match('/^(\((Koken::)?\$|str_replace\(|\(?empty\()/', $value)) {
            $value = '"' . $value;
        }
        if (substr_count($value, '"') % 2 !== 0) {
            $value .= '"';
        }
        $value = str_replace('. "" .', '.', $value);
        if ($name === 'href') {
            $value = "<?php echo ( strpos($value, '/') === 0 ? Koken::\$location['root'] . $value : $value ) . ( Koken::\$preview ? '&amp;preview=' . Koken::\$preview : '' ); ?>";
        } else {
            $value = "<?php echo $value; ?>";
        }
        return "$name=\"$value\"";
    }

    private function attr_replace($matches)
    {
        $t = new Tag();

        if (strpos($matches[1], '.replace(') !== false) {
            preg_match('/(.*)\.replace\((.*)\)/', $matches[1], $r_matches);
            $data = $t->field_to_keys($r_matches[1]);
            return 'str_replace(' . $r_matches[2] . ', ' . $data . ')';
        }

        $modifier = isset($matches[2]) ? $matches[2] : '';
        return '" . (' . $t->field_to_keys($matches[1]) . $modifier . ') . "';
    }

    private static function callback($matches)
    {
        $out = '';
        list($full, $start, $closing, $action) = $matches;
        $closing = $closing === '/';
        $attr = $start !== '<';

        if (isset($matches[4])) {
            preg_match_all('/([:a-z_\-]+)="([^"]+?)?"/', $matches[4], $param_matches);
            $parameters = [];
            $parameters['api'] = [];

            foreach ($param_matches[1] as $index => $key) {
                if (strpos($key, 'api:') === 0) {
                    $key = str_replace('api:', '', $key);
                    $parameters['api'][$key] = $param_matches[2][$index];
                } else {
                    $parameters[$key] = $param_matches[2][$index];
                }
            }

            if (empty($parameters['api'])) {
                unset($parameters['api']);
            }
        } else {
            $parameters = [];
        }

        if (isset($matches[5])) {
            $self_closing = trim($matches[5]) === '/';
        } else {
            $self_closing = false;
        }

        if ($attr) {
            $out = preg_replace_callback('/koken:([a-z\-]+)="([^"]+?)"/', array('Koken', 'attr_callback'), $full);
        } elseif ($action === 'else') {
            $else_tag = self::$last[self::$level-1];
            $out .= $else_tag->do_else();
            if ($else_tag->untokenize_on_else) {
                array_shift(self::$tokens);
                $else_tag->tokenize = false;
            }
        } else {
            $action = preg_replace_callback(
                '/_([a-zA-Z])/',
                function ($matches) {
                    return strtoupper($matches[1]);
                },
                $action
            );

            if ($action === 'not') {
                $action = 'if';
                $parameters['_not'] = true;
            } elseif ($action === 'pop') {
                $action = 'shift';
                $parameters['_pop'] = true;
            } elseif ($action === 'permalink') {
                $action = 'link';
                $parameters['echo'] = true;
            } elseif ($action === 'previous') {
                $action = 'next';
                $parameters['_prev'] = true;
            } elseif (in_array($action, array('content', 'essays', 'albums', 'categories', 'topics'))) {
                $parameters['_obj'] = $action;
                $action = 'loader';
            }

            $klass = 'Tag' . ucwords($action);
            $t = new $klass($parameters);

            if (!$closing) {
                if (!$self_closing) {
                    self::$last[self::$level] = $t;
                }

                if ($t->tokenize) {
                    $token = md5(uniqid('', true));
                    array_unshift(self::$tokens, $token);
                }

                $out .= trim($t->generate());

                if ($t->tokenize) {
                    $token = self::$tokens[0];
                    $out .= "<?php Koken::\$current_token = \$value$token; ?>";
                    if (isset(self::$tokens[1])) {
                        $parent = self::$tokens[1];
                        $out .= "<?php Koken::\$_parent = \$value$parent;  ?>";
                    }
                }
            }

            if ($self_closing || $closing) {
                if (!$self_closing && isset(self::$last[self::$level-1]) && method_exists(self::$last[self::$level-1], 'close')) {
                    $close_tag = self::$last[self::$level-1];
                    $out .= $close_tag->close();

                    if ($close_tag->tokenize) {
                        array_shift(self::$tokens);
                        if (count(self::$tokens)) {
                            $out .= '<?php Koken::$current_token = $value' . self::$tokens[0] . '; ?>';
                        }
                        if (isset(self::$tokens[1])) {
                            $parent = self::$tokens[1];
                            $out .= "<?php Koken::\$_parent = \$value$parent;  ?>";
                        }
                    }
                } elseif (method_exists($t, 'close')) {
                    $out .= $t->close();

                    if ($t->tokenize && !$t->untokenize_on_else) {
                        array_shift(self::$tokens);
                        if (count(self::$tokens)) {
                            $out .= '<?php Koken::$current_token = $value' . self::$tokens[0] . '; ?>';
                        }
                        if (isset(self::$tokens[1])) {
                            $parent = self::$tokens[1];
                            $out .= "<?php Koken::\$_parent = \$value$parent;  ?>";
                        }
                    }
                }

                if ($closing) {
                    self::$level--;
                }
            } else {
                self::$level++;
            }
        }

        return $out;
    }

    private static function prep_api($url, $cache = true)
    {
        if (strpos($url, 'api.php?') !== false) {
            $bits = explode('api.php?', $url);
            $url = $bits[1];
        }

        if (self::$draft && !is_null(self::$site['draft_id'])) {
            $url .= '/draft_context:' . self::$site['draft_id'];
        } elseif (self::$preview) {
            $url .= '/draft_context:' . self::$preview;
        }

        $url = Shutter::filter('site.api_url', $url);

        if ($cache) {
            $cache = Shutter::get_cache('api' . $url);
        }

        if ($cache) {
            return json_decode($cache['data'], true);
        } else {
            return $url;
        }
    }

    private static function curl_setup($url, $curl = false, $method = 'GET', $params = array())
    {
        if (!$curl) {
            $curl = curl_init();
        }

        $headers = array(
            'Connection: Keep-Alive',
            'Keep-Alive: 2',
            'Cache-Control: must-revalidate'
        );

        if (LOOPBACK_HOST_HEADER) {
            $host = $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'];
            $headers[] = 'Host: ' . $_SERVER['HTTP_HOST'];
        } else {
            $host = $_SERVER['HTTP_HOST'];
        }

        curl_setopt($curl, CURLOPT_URL, self::$protocol . '://' . $host . self::$location['real_root_folder'] . '/api.php?' . $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1944.0 Safari/537.36');

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $method = strtoupper($method);
        if ($method === 'POST') {
            // If its an array (instead of a query string) then format it correctly
            if (is_array($params)) {
                $params = http_build_query($params, null, '&');
            }

            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        }

        if (ENVIRONMENT === 'development') {
            curl_setopt($curl, CURLOPT_COOKIE, 'XDEBUG_SESSION=PHPSTORM');
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        }

        if (self::$protocol === 'https') {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }
        return $curl;
    }

    private static function is_really_callable($functions)
    {
        $disabled_functions = explode(',', str_replace(' ', '', ini_get('disable_functions')));

        if (ini_get('suhosin.executor.func.blacklist')) {
            $disabled_functions = array_merge($disabled_functions, explode(',', str_replace(' ', '', ini_get('suhosin.executor.func.blacklist'))));
        }

        if (!is_array($functions)) {
            $functions = array($functions);
        }


        foreach ($functions as $f) {
            if (in_array($f, $disabled_functions) || !is_callable($f)) {
                return false;
            }
        }

        return true;
    }

    public static function api($url, $method = 'GET', $params = array())
    {
        if (is_array($url)) {
            // Shared hosts are crazy, so let's really make sure curl_multi works
            if (
                MAX_PARALLEL_REQUESTS_SITE >= count($url) &&
                self::is_really_callable(
                    array('curl_multi_init', 'curl_multi_add_handle', 'curl_multi_getcontent', 'curl_multi_remove_handle', 'curl_multi_close', 'curl_multi_exec')
                )
            ) {
                $return = [];
                $curls = [];

                foreach ($url as $index => $u) {
                    if (is_array($u) && empty($u)) {
                        $return[$index] = [];
                    } else {
                        $data = self::prep_api($u);

                        if (is_array($data)) {
                            $return[$index] = $data;
                        } else {
                            $curls[] = array(
                                'index' => $index,
                                'url' => $data
                            );
                        }
                    }
                }

                if (!empty($curls)) {
                    if (count($curls) === 1) {
                        $start = microtime(true);
                        $return[$curls[0]['index']] = self::api($curls[0]['url']);
                        $end = microtime(true);
                        self::$timers[$curls[0]['url']] = $end - $start;
                    } else {
                        $start = microtime(true);

                        $mh = curl_multi_init();

                        foreach ($curls as &$ch) {
                            $ch['curl'] = self::curl_setup($ch['url']);
                            curl_multi_add_handle($mh, $ch['curl']);
                        }

                        // kick off the requests
                        do {
                            $mrc = curl_multi_exec($mh, $active);
                            curl_multi_select($mh);
                        } while ($active > 0);

                        $timer_urls = [];

                        foreach ($curls as $c) {
                            $timer_urls[] = $c['url'];
                            $return[$c['index']] = json_decode(curl_multi_getcontent($c['curl']), true);
                            curl_multi_remove_handle($mh, $c['curl']);
                        }

                        curl_multi_close($mh);

                        $end = microtime(true);
                        self::$timers[join("\n\t", $timer_urls)] = $end - $start;
                    }
                }
            } else {
                foreach ($url as $index => $u) {
                    $return[] = self::api($u);
                }
            }

            foreach ($return as $data) {
                $data = Shutter::filter('site.api_data', $data);
            }

            return $return;
        } else {
            $cache = $method !== 'POST';
            $data = self::prep_api($url, $cache);

            if (!is_array($data)) {
                if (!self::$curl_handle) {
                    self::$curl_handle = curl_init();
                }

                $curl = self::curl_setup($data, self::$curl_handle, $method, $params);

                $start = microtime(true);
                $data = json_decode(curl_exec($curl), true);
                $end = microtime(true);

                if ($curl !== self::$curl_handle) {
                    curl_close($curl);
                }

                self::$timers[$url] = $end - $start;
            }

            $data = Shutter::filter('site.api_data', $data);

            return $data;
        }
    }

    public static function get_path($relative_path, $relative = false)
    {
        $folders = array_merge(
            array(self::$template_path),
            Shutter::get_template_folders(),
            array(self::$fallback_path)
        );

        foreach ($folders as $folder) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $folder) . '/' . $relative_path;

            if (file_exists($path)) {
                if ($relative) {
                    $parts = explode('/storage/', $path);

                    $storage_path = array_pop($parts);

                    return self::$location['real_root_folder'] . '/storage/' . $storage_path;
                }

                return $path;
            }
        }

        return false;
    }

    public static function redirect($url, $parameters)
    {
        $url = self::$location['root'] . $url;

        if (!empty($parameters)) {
            $parameters = implode('&', array_map(function ($key, $val) {
                return "$key=" . urlencode($val);
            }, array_keys($parameters), $parameters));

            $url .= (self::$rewrite ? '?' : '&') . $parameters;
        }

        header("Location: $url");
        exit;
    }

    public static function render($raw)
    {
        ob_start();
        eval('?>' . $raw);
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    public static function cache($contents)
    {
        $buster = self::$root_path . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'no-site-cache';

        $cache_path = Shutter::filter('site.cache.write.path', self::$cache_path);

        if (isset($cache_path) && error_reporting() == 0 && !file_exists($buster)) {
            Shutter::write_cache($cache_path, $contents);
        }
    }

    private static function case_convert($str, $case)
    {
        switch ($case) {
            case 'lower':
                $str = function_exists('mb_strtolower') ? mb_strtolower($str) : strtolower($str);
                break;

            case 'upper':
                $str = function_exists('mb_strtoupper') ? mb_strtoupper($str) : strtoupper($str);
                break;

            case 'title':
                $str = function_exists('mb_convert_case') ? mb_convert_case($str, MB_CASE_TITLE) : ucwords($str);
                break;

            case 'sentence':
                $str = function_exists('mb_substr') ? mb_strtoupper(mb_substr($str, 0, 1)) . mb_strtolower(mb_substr($str, 1)) : ucfirst($str);
                break;
        }
        return $str;
    }
    public static function out($key)
    {
        if ($key === 'count' && isset(self::$current_token['__loop__'])) {
            return count(self::$current_token['__loop__']);
        }

        $parameters = array( 'separator' => ' ', 'utc' => true, 'autolink' => null );
        preg_match_all('/([a-z_]+)=["\']([^"\']+)["|\']/', $key, $matches);
        foreach ($matches[1] as $i => $name) {
            $key = str_replace($matches[0][$i], '', $key);
            $parameters[$name] = $matches[2][$i];
        }

        $is_archive = strpos($key, 'archive.type') !== false;

        $key = str_replace(' ', '', $key);
        $count = $plural = $singular = $math = $to_json = false;
        if (strpos($key, '|') === false) {
            $globals = array(
                'site', 'location', '_parent', 'rss', 'profile', 'source', 'settings', 'routed_variables', 'page_variables', 'pjax', 'labels', 'messages', 'language'
            );

            if (strpos($key, '.length') !== false) {
                $key = str_replace('.length', '', $key);
                $count = true;
            }

            if (preg_match('/_on$/', $key)) {
                $key .= '.timestamp';
                if (!isset($parameters['date_format'])) {
                    if (isset($parameters['date_only'])) {
                        $parameters['date_format'] = self::$site['date_format'];
                    } elseif (isset($parameters['time_only'])) {
                        $parameters['date_format'] = self::$site['time_format'];
                    } else {
                        $parameters['date_format'] = self::$site['date_format'] . ' ' . self::$site['time_format'];
                    }
                }
            }

            if (preg_match('~\s*([+\-/\*])\s*(([0-9]+)|([a-z_\.]+))\s*?~', $key, $maths)) {
                $math = array('operator' => $maths[1], 'num' => is_numeric($maths[2]) ? $maths[2] : self::out($maths[2]));
                $key = str_replace($maths[0], '', $key);
            }

            $keys = explode('.', $key);

            if (in_array($keys[0], $globals)) {
                $global_key = array_shift($keys);
                if ($global_key === 'labels') {
                    $return = self::$site['url_data'];
                } elseif ($global_key === 'rss') {
                    $return = self::$rss_feeds;
                } else {
                    $return = self::$$global_key;
                }
            } elseif (in_array($key, self::$template_variable_keys)) {
                return self::$template_variables[$key];
            } else {
                $return = self::$current_token;
            }

            if (count($keys) === 1 && $keys[0] === 'now') {
                $return = time();
            } elseif (count($keys) === 1 && $keys[0] === 'year') {
                $return = date('Y');
            } else {
                while (count($keys)) {
                    $index = array_shift($keys);
                    if ($index === 'index') {
                        $index = '__loop_index';
                    } elseif ($index === 'first') {
                        $index = 0;
                    }

                    if (is_array($return) && isset($return['utc'])) {
                        $parameters['utc'] = $return['utc'];
                    }

                    if ((!is_array($return) || !isset($return[$index])) && $index === 'type' && isset($return['__koken__'])) {
                        if (count($keys)) {
                            $next = array_shift($keys);
                        } else {
                            $next = 'plural';
                        }
                        $return = self::$site['url_data'][$return['__koken__']][$next];

                        if (!isset($parameters['case'])) {
                            $parameters['case'] = 'title';
                        }
                        $plural = $singular = false;
                        $keys = [];
                    } elseif ((!is_array($return) || !isset($return[$index])) && $index === 'clean' && !isset($return['raw'])) {
                        $parameters['clean'] = true;
                    } elseif (!isset($return[$index]) && $index === 'title' && isset($return['year'])) {
                        if (isset($return['month'])) {
                            $return = self::title_from_archive($return, isset($parameters['date_format']) ? $parameters['date_format'] : false);
                        } else {
                            $return = $return['year'];
                        }
                        unset($parameters['date_format']);
                    } elseif (isset($global_key) && $global_key === 'settings' && isset(Koken::$settings['__scoped_' . str_replace('.', '-', Koken::$location['template']) . '_' . $index])) {
                        $return = Koken::$settings['__scoped_' . str_replace('.', '-', Koken::$location['template']) . '_' . $index];
                    } else {
                        if (($index === 'plural' || $index === 'singular') && $is_archive && isset(self::$site['url_data'][$return]) && isset(self::$site['url_data'][$return][$index])) {
                            $return = self::$site['url_data'][$return][$index];

                            if (!isset($parameters['case'])) {
                                $parameters['case'] = 'lower';
                            }
                        } else {
                            if ($index === 'to_json') {
                                $to_json = true;
                            } elseif ($index === 'plural' && (!is_array($return) || !isset($return['plural']))) {
                                $plural = true;
                            } elseif ($index === 'singular' && (!is_array($return) || !isset($return['singular']))) {
                                $singular = true;
                            } else {
                                $return = isset($return[$index]) ? $return[$index] : '';

                                if (is_string($return)) {
                                    $parts = explode('.', $return);
                                    if (in_array($parts[0], $globals)) {
                                        $return = self::out($return);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $candidates = explode('|', $key);
            $return = '';
            while (empty($return) && count($candidates)) {
                $return = self::out(array_shift($candidates));
            }
        }

        if ($count) {
            $return = count($return);

            if (isset($parameters['plural']) && isset($parameters['singular']) && is_numeric($return)) {
                $pparts = explode('.', $parameters['plural']);
                $sparts = explode('.', $parameters['singular']);

                if (in_array($pparts[0], $globals)) {
                    $parameters['plural'] = self::out($parameters['plural']);
                }

                if (in_array($sparts[0], $globals)) {
                    $parameters['singular'] = self::out($parameters['singular']);
                }

                return $return === 1 ? $parameters['singular'] : $parameters['plural'];
            } else {
                return $return;
            }
        } else {
            if (isset($parameters['truncate'])) {
                $return = self::truncate(strip_tags($return), $parameters['truncate'], isset($parameters['after_truncate']) ? $parameters['after_truncate'] : '…');
            }

            if (isset($parameters['case'])) {
                $return = self::case_convert($return, $parameters['case']);
            }

            if (isset($parameters['paragraphs'])) {
                $return = self::format_paragraphs($return);

                if (is_null($parameters['autolink']) || $parameters['autolink']) {
                    $return = autolink($return);
                }
            } elseif ($parameters['autolink']) {
                $return = autolink($return);
            }

            if (isset($parameters['date_format'])) {
                if (is_array($return) && isset($return['timestamp'])) {
                    $return = $return['timestamp'];
                }
                if (!$parameters['utc']) {
                    date_default_timezone_set('UTC');
                }
                $return = date($parameters['date_format'], $return);
                date_default_timezone_set(Koken::$site['timezone']);
            }

            if (isset($parameters['strip_html'])) {
                $return = preg_replace('/\s+/', ' ', preg_replace('/\n+/', ' ', strip_tags($return)));
                $parameters['html_encode'] = true;
            }

            if (isset($parameters['find']) && isset($parameters['replace'])) {
                $return = str_replace($parameters['find'], $parameters['replace'], $return);
            }

            if (isset($parameters['collate'])) {
                $args = explode(',', str_replace(' ', '', $parameters['collate']));

                foreach ($args as &$a) {
                    $a = self::out(trim($a));
                }
                unset($a);

                $return = vsprintf($return, $args);
            }

            if (isset($parameters['url_encode'])) {
                $return = urlencode($return);
            }

            if (isset($parameters['html_encode'])) {
                $return = htmlspecialchars($return);
            }

            if (isset($parameters['if_true'])) {
                $return = $return ? $parameters['if_true'] : '';
            }

            if (isset($parameters['clean'])) {
                $return = preg_replace('/\s+/', ' ', preg_replace('/[^\-_A-Za-z0-9]+/', ' ', preg_replace('/\.[a-z]+$/', '', $return)));
            }

            if (isset($parameters['plural']) && isset($parameters['singular']) && is_numeric($return)) {
                $pparts = explode('.', $parameters['plural']);
                $sparts = explode('.', $parameters['singular']);

                if (in_array($pparts, $globals)) {
                    $parameters['plural'] = self::out($parameters['plural']);
                }

                if (in_array($sparts, $globals)) {
                    $parameters['singular'] = self::out($parameters['singular']);
                }

                $return = $return === 1 ? $parameters['singular'] : $parameters['plural'];
            }

            if ($plural) {
                $return = self::plural($return);
            }

            if ($singular) {
                $return = self::singular($return);
            }

            if ($math) {
                switch ($math['operator']) {
                    case '+':
                        $return += $math['num'];
                        break;

                    case '-':
                        $return -= $math['num'];
                        break;

                    case '/':
                        $return /= $math['num'];
                        break;

                    case '*':
                        $return *= $math['num'];
                        break;
                }
            }

            if (is_array($return) && !isset($parameters['debug'])) {
                if ($to_json) {
                    unset($return['counts']);

                    $fields = false;
                    if (isset($parameters['fields'])) {
                        $fields = explode(',', str_replace(' ', '', $parameters['fields']));
                    }

                    if (isset($return[0])) {
                        $fresh = [];
                        foreach ($return as $r) {
                            if ($fields) {
                                $slim = [];
                                foreach ($fields as $f) {
                                    if (isset($r[$f])) {
                                        $slim[$f] = $r[$f];
                                    }
                                }
                                $fresh[] = $slim;
                            } else {
                                $fresh[] = $r;
                            }
                        }
                        $return = $fresh;
                    } elseif ($fields) {
                        $slim = [];
                        foreach ($fields as $f) {
                            if (isset($return[$f])) {
                                $slim[$f] = $return[$f];
                            }
                        }
                        $return = $slim;
                    }

                    $return = json_encode($return);
                } elseif (isset($return['clean'])) {
                    $return = $return['clean'];
                } elseif (isset($return['raw'])) {
                    $return = $return['raw'];
                } elseif (count($return)) {
                    if (is_array($return[0])) {
                        if (!isset($parameters['field'])) {
                            $parameters['field'] = 'title';
                        }

                        if (isset($parameters['field']) && isset($return[0][$parameters['field']])) {
                            $return = array_map(function ($arr) use ($parameters) {
                                return $arr[$parameters['field']];
                            }, $return);
                        } else {
                            $return = [];
                        }
                    }

                    $return = implode($parameters['separator'], $return);
                } else {
                    $return = '';
                }
            }

            return isset($parameters['debug']) ? var_dump($return) : $return;
        }
    }

    private static function singular($str)
    {
        $result = strval($str);

        $singular_rules = array(
            '/(matr)ices$/'         => '\1ix',
            '/(vert|ind)ices$/'     => '\1ex',
            '/^(ox)en/'             => '\1',
            '/(alias)es$/'          => '\1',
            '/([octop|vir])i$/'     => '\1us',
            '/(cris|ax|test)es$/'   => '\1is',
            '/(shoe)s$/'            => '\1',
            '/(o)es$/'              => '\1',
            '/(bus|campus)es$/'     => '\1',
            '/([m|l])ice$/'         => '\1ouse',
            '/(x|ch|ss|sh)es$/'     => '\1',
            '/(m)ovies$/'           => '\1\2ovie',
            '/(s)eries$/'           => '\1\2eries',
            '/([^aeiouy]|qu)ies$/'  => '\1y',
            '/([lr])ves$/'          => '\1f',
            '/(tive)s$/'            => '\1',
            '/(hive)s$/'            => '\1',
            '/([^f])ves$/'          => '\1fe',
            '/(^analy)ses$/'        => '\1sis',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/' => '\1\2sis',
            '/([ti])a$/'            => '\1um',
            '/(p)eople$/'           => '\1\2erson',
            '/(m)en$/'              => '\1an',
            '/(s)tatuses$/'         => '\1\2tatus',
            '/(c)hildren$/'         => '\1\2hild',
            '/(n)ews$/'             => '\1\2ews',
            '/([^u])s$/'            => '\1',
        );

        foreach ($singular_rules as $rule => $replacement) {
            if (preg_match($rule, $result)) {
                $result = preg_replace($rule, $replacement, $result);
                break;
            }
        }

        return $result;
    }

    private static function plural($str, $force = false)
    {
        $result = strval($str);

        $plural_rules = array(
            '/^(ox)$/'                 => '\1\2en',     // ox
            '/([m|l])ouse$/'           => '\1ice',      // mouse, louse
            '/(matr|vert|ind)ix|ex$/'  => '\1ices',     // matrix, vertex, index
            '/(x|ch|ss|sh)$/'          => '\1es',       // search, switch, fix, box, process, address
            '/([^aeiouy]|qu)y$/'       => '\1ies',      // query, ability, agency
            '/(hive)$/'                => '\1s',        // archive, hive
            '/(?:([^f])fe|([lr])f)$/'  => '\1\2ves',    // half, safe, wife
            '/sis$/'                   => 'ses',        // basis, diagnosis
            '/([ti])um$/'              => '\1a',        // datum, medium
            '/(p)erson$/'              => '\1eople',    // person, salesperson
            '/(m)an$/'                 => '\1en',       // man, woman, spokesman
            '/(c)hild$/'               => '\1hildren',  // child
            '/(buffal|tomat)o$/'       => '\1\2oes',    // buffalo, tomato
            '/(bu|campu)s$/'           => '\1\2ses',    // bus, campus
            '/(alias|status|virus)/'   => '\1es',       // alias
            '/(octop)us$/'             => '\1i',        // octopus
            '/(ax|cris|test)is$/'      => '\1es',       // axis, crisis
            '/s$/'                     => 's',          // no change (compatibility)
            '/$/'                      => 's',
        );

        foreach ($plural_rules as $rule => $replacement) {
            if (preg_match($rule, $result)) {
                $result = preg_replace($rule, $replacement, $result);
                break;
            }
        }

        return $result;
    }

    public static function get_default_link($name)
    {
        $name = rtrim($name, 's');
        $template = self::$location['urls'][$name];
        preg_match('~^/([^/]+)~', $template, $matches);
        return $matches[0];
    }

    public static function link($parameters)
    {
        $defaults = array(
            'to' => false,
            'bind_to_key' => false,
            'url' => false,
            'data' => false,
            'filter:id' => false,
            'echo' => false,
            'lightbox' => false,
            'share' => false,
        );

        $options = array_merge($defaults, $parameters);

        $attributes = array('class' => '');
        $tail = '';
        $type = $token = $context = false;
        $url = '';

        if ($options['bind_to_key']) {
            $attributes['data-bind-to-key'] = $options['bind_to_key'];
        }

        if ($options['share']) {
            $attributes['data-koken-share'] = $options['share'];
            $share_parameters = [];

            $label = ucwords($options['share']);

            $token = self::$current_token;

            $title_getter = 'title|filename';
            $url_getter = 'url';
            $caption_getter = 'excerpt|caption|summary';
            $tags_getter = 'tags';

            if (isset($token['album'])) {
                $title_getter = 'album.title';
                $url_getter = 'album.url';
                $caption_getter = 'album.summary|album.description';
                $tags_getter = 'album.tags';
            }

            $attributes['title'] = self::out('language.share') . '&nbsp;&quot;' . self::out($title_getter . ' html_encode="true"') . '&quot;&nbsp;'. self::out('language.on') . '&nbsp;' . $label;

            $share_url = self::out($url_getter . ' url_encode="true"');

            $image = false;

            if (isset($token['presets'])) {
                $image = $token['presets']['large']['url'];
            } elseif (isset($token['album']) && count($token['album']['covers'])) {
                $image = $token['album']['covers'][0]['presets']['large']['url'];
            } elseif (isset($token['featured_image']) && $token['featured_image']) {
                $image = $token['featured_image']['presets']['large']['url'];
            }

            if ($image) {
                if (strpos($image, 'http') !== 0) {
                    $image = Koken::$location['site_url'] . $image;
                }

                $image = urlencode(str_replace(',', '/', $image));
            }

            $tags = false;

            if (isset($token['tags']) || isset($token['album']['tags'])) {
                $tags = str_replace(' ', ',', self::out($tags_getter));
            }

            switch ($options['share']) {
                case 'twitter':
                    $base_url = 'https://twitter.com/intent/tweet';
                    $share_parameters['text'] = self::out($title_getter . ' url_encode="true"');
                    $share_parameters['url'] = $share_url;

                    if (!empty(self::$site['profile']['twitter'])) {
                        $share_parameters['via'] = self::$site['profile']['twitter'];
                    }

                    break;

                case 'facebook':
                    $base_url = 'https://www.facebook.com/sharer.php';
                    $share_parameters['u'] = $share_url;
                    break;

                case 'google-plus':
                    $base_url = 'https://plus.google.com/share';
                    $share_parameters['url'] = $share_url;
                    break;

                case 'pinterest':
                    $base_url = 'http://pinterest.com/pin/create/button/';
                    $share_parameters['url'] = $share_url;
                    if ($image) {
                        $share_parameters['description'] = self::out($title_getter . ' url_encode="true"');
                        $share_parameters['media'] = $image;
                    }
                    break;

                case 'tumblr':
                    $base_url = 'https://www.tumblr.com/widgets/share/tool';

                    $share_title_unencoded = self::out($title_getter);
                    $share_parameters['caption'] = '<p><strong><a href="' . self::out($url_getter) . '" title="' . $share_title_unencoded . '">' . $share_title_unencoded . '</a></strong></p>';
                    $share_parameters['caption'] .= '<p>' . self::out($caption_getter) . '</p>';
                    $share_parameters['caption'] = urlencode($share_parameters['caption']);

                    $share_parameters['canonicalUrl'] = $share_url;

                    if ($image) {
                        $share_parameters['posttype'] = 'photo';
                        $share_parameters['content'] = $image;
                    } else {
                        $share_parameters['posttype'] = 'link';
                        $share_parameters['content'] = $share_url;
                    }

                    if ($tags) {
                        $share_parameters['tags'] = $tags;
                    }
                    break;

                default:
                    $url = '<a>';
                    break;
            }

            if (isset($base_url)) {
                $share_param = [];
                foreach ($share_parameters as $key => $val) {
                    $share_param[] = $key . '=' . $val;
                }

                $url = $base_url . '?' . join('&amp;', $share_param);
            }

            foreach ($parameters as $key => $val) {
                if (strpos($key, 'filter:') === 0) {
                    continue;
                }

                if (!array_key_exists($key, $defaults)) {
                    $attributes[$key] = $val;
                }
            }
        } else {
            if ($options['to'] === 'date') {
                $type = 'event_timeline';
            } elseif (in_array($options['to'], array('archive', 'tag', 'category', 'tag_contents', 'tag_albums', 'tag_essays', 'category_contents', 'category_albums', 'category_essays'))) {
                $type = $options['to'];
            } elseif ($options['to'] && strpos($options['to'], 'archive_')) {
                $type = str_replace('archive_', '', $options['to']);
            }

            if ($options['url']) {
                $url = $options['url'];
            } elseif (!$type && $options['to']) {
                if ($options['filter:id']) {
                    $model = $options['to'];
                    if ($model === 'page' || $model === 'essay') {
                        $model = 'text';
                    } elseif ($model !== 'content') {
                        $model .= 's';
                    }
                    $data = self::api("/$model/" . $options['filter:id']);
                    $url = $data['__koken_url'];
                } else {
                    if (isset(self::$location['urls'][$options['to']])) {
                        $url = self::$location['urls'][$options['to']];
                    } elseif (isset(self::$template_routes[$options['to']])) {
                        $url = self::$template_routes[$options['to']];
                    } else {
                        $url = '/';
                    }
                }
            } elseif ($options['data']) {
                $token = $options['data'];
            } else {
                $token = self::$current_token;
                $check_token = self::$_parent ? self::$_parent : $token;

                if (isset($check_token['context']) && isset($check_token['context']['album'])) {
                    $context = $check_token['context']['album'];
                } elseif (isset($check_token['album'])) {
                    $context = $check_token['album'];
                }
            }

            if ($token && $type) {
                if (in_array($type, array('event_timeline', 'tag', 'category', 'tag_contents', 'tag_albums', 'tag_essays', 'category_contents', 'category_albums', 'category_essays'))) {
                    $token['__koken__override'] = $type;
                } elseif ($type === 'archive') {
                    $token['__koken__override'] = 'archive_' . (isset($token['__koken__']) ? $token['__koken__'] . 's' : (isset($token['album']) ? 'albums' : ''));
                } else {
                    $token['__koken__override'] = $token['__koken__'] . '_' . $type;
                }
            }

            foreach ($parameters as $key => $val) {
                if ($key === 'filter:id') {
                    continue;
                }
                if (strpos($key, 'filter:') === 0) {
                    $tail .= str_replace('filter:', '', $key) . ':' . $val . '/';
                    if ($key === 'filter:order_by' && $token) {
                        $token['__koken__override_date'] = $val;
                    }
                } elseif (!array_key_exists($key, $defaults)) {
                    $attributes[$key] = $val;
                }
            }

            if ($token) {
                $url = self::form_link($token, $context, $options['lightbox']);
            }

            if ($url === self::$navigation_home_path) {
                $url = '/';
            }

            // Checking for is_current here prevents issues with pagination links as described in https://github.com/koken/koken/issues/1210
            if (($url === '/' && self::$location['here'] === '/') || ($token && !isset($token['is_current']) && preg_match('/^' . str_replace('/', '\\/', urldecode($url)) . '(\\/.*)?$/', self::$location['here']))) {
                $attributes['class'] .= ' k-nav-current';
            }

            if ($options['lightbox']) {
                $attributes['class'] .= ' k-link-lightbox';
            }

            if (preg_match('~\.rss$~', $url)) {
                $attributes['target'] = '_blank';
            } elseif (strpos($url, '/') === 0 && !preg_match('~/lightbox/$~', $url)) {
                $attributes['data-koken-internal'] = true;
            }

            if (strpos($url, '/') === 0) {
                $url = self::$location['root'] . $url . $tail;
                if (self::$preview) {
                    $url .= '&amp;preview=' . self::$preview;
                }
            }
        }

        if ($options['echo']) {
            $protocol = self::find_protocol();
            $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $url;
            echo $url;
        } else {
            $att = [];
            foreach ($attributes as $key => $val) {
                if ($val === true) {
                    $att[] = $key;
                } elseif (!empty($val)) {
                    $att[] = "$key=\"" . trim($val) . '"';
                }
            }
            $att = implode(' ', $att);
            echo "<a href=\"$url\" $att>";
        }
    }

    public static function form_link($obj, $ctx, $lightbox)
    {
        if (isset($obj['link'])) {
            return $obj['link'];
        }

        $use_tail = false;

        if (isset($obj['__koken_url']) && !isset($obj['__koken__override'])) {
            $url = $obj['__koken_url'];
            $use_tail = true;
        } else {
            $defaults = self::$location['urls'];
            if (isset($obj['__koken__override'])) {
                $type = $obj['__koken__override'];

                if (isset($obj['album'])) {
                    $obj = $obj['album'];
                }

                if ((!self::$source || strpos(self::$source['type'], 'event') !== 0) && $type === 'event_timeline' && in_array($obj['__koken__'], array('content', 'album', 'essay')) && isset($defaults['event_timeline'])) {
                    $type = 'event_timeline';
                }
            } elseif (!isset($obj['__koken__']) && isset($obj['items']) && isset($obj['year'])) {
                $type = 'event_timeline';
            } else {
                if (isset($obj['album'])) {
                    $obj = $obj['album'];
                } elseif (isset($obj['event'])) {
                    $obj = $obj['event'];
                }

                $type = $obj['__koken__'];
            }

            $url = '';

            if ($type === 'album' && $obj['album_type'] === 'set') {
                $type = 'set';
            } elseif ($type === 'event') {
                $type = 'event_timeline';
            }

            if (isset($defaults[$type])) {
                if (!$defaults[$type] && $type === 'set') {
                    $type = 'album';
                }

                if ($defaults[$type]) {
                    if (strpos($type, 'tag') === 0) {
                        $obj['id'] = $obj['title'];
                        if (is_numeric($obj['id'])) {
                            $obj['id'] = 'tag-' . $obj['id'];
                        }
                    }

                    if (isset($obj['internal_id'])) {
                        $obj['id'] = $obj['slug'] = $obj['internal_id'];
                    }

                    $url = $defaults[$type];

                    if (isset($obj['__koken__']) && $obj['__koken__'] === 'content' && isset($ctx['id']) && is_numeric($ctx['id'])) {
                        $obj['content_slug'] = $obj['slug'];
                        $obj['content_id'] = $obj['id'];
                        if (isset($ctx['internal_id'])) {
                            $obj['id'] = $obj['slug'] = $ctx['internal_id'];
                        } else {
                            $obj['slug'] = $ctx['slug'];
                            $obj['id'] = $ctx['id'];
                        }
                    }

                    if (!isset($obj['slug']) && isset($obj['tag'])) {
                        $obj['slug'] = $obj['tag']['title'];
                        if (is_numeric($obj['slug'])) {
                            $obj['slug'] = 'tag-' . $obj['slug'];
                        }
                    }

                    if (isset($obj['date'])) {
                        if (isset($obj['__koken__override_date']) && isset($obj[$obj['__koken__override_date']])) {
                            $date = $obj[$obj['__koken__override_date']];
                        } else {
                            $date = $obj['date'];
                        }
                        $obj['year'] = date('Y', $date['timestamp']);
                        $obj['month'] = date('m', $date['timestamp']);
                        if (isset($type) && $type === 'event_timeline') {
                            $obj['day'] = date('d', $obj['date']['timestamp']);
                        }
                    }

                    preg_match_all('/:([a-z_]+)/', $url, $matches);

                    foreach ($matches[1] as $magic) {
                        if (!isset($obj[$magic])) {
                            $obj[$magic] = '';
                        }
                        $url = str_replace(':' . $magic, urlencode($obj[$magic]), $url);
                    }

                    $url = str_replace('(?:', '', $url);
                    $url = str_replace(')?', '', $url);
                }
            }
        }

        $url = rtrim($url, '/');

        if ($lightbox && (!isset($obj['album_type']) || $obj['album_type'] !== 'set')) {
            $url = preg_replace('~/?lightbox$~', '', $url) . '/lightbox';
        }

        if (isset($token['__koken__override'])) {
            unset($token['__koken__override']);
        }

        if (isset($token['__koken__override_date'])) {
            unset($token['__koken__override_date']);
        }

        $url .= '/';

        if ($use_tail) {
            $url .= self::$link_tail;
        }

        return $url;
    }

    public static function context_parameters($type = 'content')
    {
        if ($type === 'content') {
            $params = '/context:';
            if (isset(Koken::$routed_variables['album_id']) || isset(Koken::$routed_variables['album_slug'])) {
                $params .= isset(Koken::$routed_variables['album_id']) ? Koken::$routed_variables['album_id'] : 'slug-' . Koken::$routed_variables['album_slug'];
            } elseif (isset(Koken::$routed_variables['tag_slug'])) {
                $params .= 'tag-' . urlencode(Koken::$routed_variables['tag_slug']);
                $order = explode(' ', self::$site['url_data']['content']['order']);
                $params .= '/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
            } elseif (isset(Koken::$routed_variables['category_slug'])) {
                $params .= 'category-' . urlencode(Koken::$routed_variables['category_slug']);
                $order = explode(' ', self::$site['url_data']['content']['order']);
                $params .= '/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
            } else {
                $featured_regex = isset(self::$site['urls']['feature']) ? preg_replace('/:([a-z_]+)/', '[^/]+', self::$site['urls']['feature']) : false;
                if (isset(self::$site['urls']['favorites']) && strpos(self::$location['here'], self::$site['urls']['favorites']) === 0) {
                    $order = explode(' ', self::$site['url_data']['favorite']['order']);
                    $params .= 'favorites';
                } elseif ($featured_regex && preg_match('~' . $featured_regex . '~', self::$location['here'])) {
                    if (isset(self::$site['url_data']['feature']['order'])) {
                        $order = explode(' ', self::$site['url_data']['feature']['order']);
                    } else {
                        $order = array('manual', 'ASC');
                    }
                    $params .= 'features';
                } else {
                    $order = explode(' ', self::$site['url_data']['content']['order']);
                    $params .= 'stream';
                }
                $params .= '/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
            }
        } else {
            if (isset(Koken::$routed_variables['tag_slug'])) {
                $params = '/context:tag-' . urlencode(Koken::$routed_variables['tag_slug']);
                $order = explode(' ', self::$site['url_data'][$type]['order']);
                $params .= '/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
            } elseif (isset(Koken::$routed_variables['category_slug'])) {
                $params = '/context:category-' . urlencode(Koken::$routed_variables['category_slug']);
                $order = explode(' ', self::$site['url_data'][$type]['order']);
                $params .= '/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
            } else {
                $order = explode(' ', self::$site['url_data'][$type]['order']);
                $params = '/context_set:1/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
            }
        }
        return $params;
    }

    public static function render_nav($data, $list, $root = false, $klass = '')
    {
        $pre = $wrap_pre = $wrap_post = '';
        $post = '&nbsp;';
        if ($list) {
            $wrap_pre = '<ul class="k-nav-list' . ($root ? ' k-nav-root' : '') . ' ' . $klass . '">';
            $wrap_post = '</ul>';
            $pre = '<li>';
            $post = '</li>';
        }

        $current_match_len = 0;
        $o = $wrap_pre;
        foreach ($data as $key => $value) {
            if (isset($value['hide'])) {
                continue;
            }

            if (strlen($value['path']) && $value['path'][0] === '/' && !preg_match('/\.rss$/', $value['path'])) {
                $value['path'] = rtrim($value['path'], '/') . '/';
            }
            if ($value['path'] === self::$navigation_home_path) {
                $value['path'] = '/';
            }
            if ($value['path'] == '/') {
                $current = self::$location['here'] === '/';
            } else {
                $current = preg_match('~^' . $value['path'] . '(.*)?$~', self::$location['here']) && strlen($value['path']) > $current_match_len;

                if ($current) {
                    $current_match_len = strlen($value['path']);
                    $o = str_replace('class="k-nav-current"', '', $o);
                    $o = str_replace('class="k-nav-current ', 'class="', $o);
                }
            }
            $o .= $pre . '<a';
            if (isset($value['target'])) {
                $o .= ' target="' . $value['target'] . '"';
            }
            $classes = [];
            if ($current) {
                $classes[] = 'k-nav-current';
            }
            if (isset($value['set'])) {
                $classes[] = 'k-nav-set';
            }
            if (count($classes)) {
                $o .= ' class="' . join(' ', $classes) . '"';
            }

            if (preg_match('~\.rss$~', $value['path'])) {
                $o .= ' target="_blank"';
            } elseif (strpos($value['path'], 'http') === false && strpos($value['path'], 'mailto:') !== 0 && !preg_match('~/lightbox/$~', $value['path'])) {
                $o .= ' data-koken-internal';
            }

            $root = $value['path'] === '/' ? preg_replace('/\/index.php$/', '', self::$location['root']) : self::$location['root'];
            $is_native = !preg_match('/^(https?|mailto)/', $value['path']);
            $o .= ' title="' . $value['label'] . '" href="' . ($is_native ? $root : '') . $value['path'] . (self::$preview && $is_native ? '&amp;preview=' . self::$preview : '') . '">' . $value['label'] . '</a>';

            if (isset($value['items']) && !empty($value['items'])) {
                $o .= self::render_nav($value['items'], $list);
            }
            $o .= $post;
        }
        return $o . $wrap_post;
    }

    public static function output_img($content, $options = array(), $params = '')
    {
        $defaults = array(
            'width' => 0,
            'height' => 0,
            'crop' => false,
            'hidpi' => self::$site['hidpi'] && !self::$rss
        );

        $options = array_merge($defaults, $options);

        $attr = array(
            $options['width'],
            $options['height']
        );

        $w = $options['width'];
        $h = $options['height'];

        if ($w ==0 && $h == 0) {
            // Responsive
            // return;
            // $w = '100%';
        }

        if (!isset($content['url']) || !isset($content['cropped'])) {
            if (!$options['crop']) {
                if ($options['width'] == 0) {
                    $w = round(($h*$content['width'])/$content['height']);
                } elseif ($options['height'] == 0) {
                    $h = round(($w*$content['height'])/$content['width']);
                } else {
                    $original_aspect = $content['aspect_ratio'];
                    $target_aspect = $w/$h;
                    if ($original_aspect >= $target_aspect) {
                        if ($w > $content['width']) {
                            $w = $content['width'];
                            $h = $content['height'];
                        } else {
                            $h = round(($w*$content['height'])/$content['width']);
                        }
                    } else {
                        if ($h > $content['height']) {
                            $w = $content['width'];
                            $h = $content['height'];
                        } else {
                            $w = round(($h*$content['width'])/$content['height']);
                        }
                    }
                }
            }

            if (!isset($content['cache_path'])) {
                $url = $content['url'];
            } else {
                $longest = max($w, $h);

                $breakpoints = array(
                    'tiny' => 60,
                    'small' => 100,
                    'medium' => 480,
                    'medium_large' => 800,
                    'large' => 1024,
                    'xlarge' => 1600,
                    'huge' => 2048
                );

                $preset_base = '';
                $last_len = false;
                foreach ($breakpoints as $name => $len) {
                    if ($longest <= $len) {
                        $diff = $len - $longest;
                        if (!$last_len || ($longest - $last_len > $diff)) {
                            $preset_base = $name;
                        }
                        break;
                    }
                    $preset_base = $name;
                    $last_len = $len;
                }

                $attr[] = self::$site["image_{$preset_base}_quality"];
                $attr[] = self::$site["image_{$preset_base}_sharpening"]*100;

                $url = $content['cache_path']['prefix'] . join('.', $attr);
                if ($options['crop']) {
                    $url .= '.crop';
                }
                if ($options['hidpi']) {
                    $url .= '.2x';
                }
                $url .= '.' . $content['cache_path']['extension'];

                $params['data-longest-side'] = $longest;
            }
        } else {
            if ($options['crop']) {
                $content = $content['cropped'];
            } elseif ($w > 0 && $h > 0) {
                $original_aspect = $content['width'] / $content['height'];
                $target_aspect = $w/$h;
                if ($original_aspect >= $target_aspect) {
                    $h = round(($w*$content['height'])/$content['width']);
                } else {
                    $w = round(($h*$content['width'])/$content['height']);
                }
            }
            $url = $content['url'];
            $params['data-longest-side'] = max($content['width'], $content['height']);
        }


        if ((isset($params['class']) && strpos($params['class'], 'k-lazy-load') !== false) || $options['hidpi']) {
            $params['data-src'] = $url;
            $noscript = true;
            $params['src'] = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        } else {
            $noscript = false;
            $params['src'] = $url;
        }

        if ($w > 0) {
            $params['width'] = $w;
        }
        if ($h > 0) {
            $params['height'] = $h;
        }

        if ($noscript) {
            $noscript_params = $params;
            $noscript_params['src'] = $noscript_params['data-src'];
            unset($noscript_params['data-src']);
            unset($noscript_params['data-longest-side']);
            $noscript = '<noscript><img ' . self::params_to_str($noscript_params) . ' /></noscript>';
        } else {
            $noscript = '';
        }

        return "$noscript<img " . self::params_to_str($params) . " />";
    }

    public static function form($params)
    {
        $options = array_merge(array('method' => 'post', 'class' => false, 'id' => false), $params);
        $out = '<form method="' . $options['method'] . '"';
        if (isset($options['url'])) {
            $out .=  ' action="' . self::$location['root'] . $options['url'] . '"';
        }
        if ($options['class']) {
            $out .= ' class="' . $options['class'] . '"';
        }

        if ($options['id']) {
            $out .= ' id="' . $options['id'] . '"';
        }
        return $out . '>';
    }
    private static function params_to_str($params)
    {
        $arr = [];
        foreach ($params as $key => $val) {
            $arr[] = "$key=\"$val\"";
        }
        return join(' ', $arr);
    }

    public static function truncate($str, $limit, $after = '…')
    {
        $len = function_exists('mb_strlen') ? mb_strlen($str) : strlen($str);

        if ($len > $limit) {
            $str = trim(function_exists('mb_substr') ? mb_substr($str, 0, $limit) : substr($str, 0, $limit)) . $after;
        }

        return $str;
    }

    public static function format_paragraphs($pee, $br = 1)
    {
        if (trim($pee) === '') {
            return '';
        }
        $pee = $pee . "\n"; // just to make things a little easier, pad the end
        $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
        // Space things out a little
        $allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
        $pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
        $pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
        $pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
        if (strpos($pee, '<object') !== false) {
            $pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
            $pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
        }
        $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
        // make paragraphs, including one at the end
        $pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
        $pee = '';
        foreach ($pees as $tinkle) {
            $pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
        }
        $pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace

        return $pee;
    }

    public static function covers($data, $min, $max)
    {
        if (!isset($data['album']['covers'])) {
            return array();
        }

        $covers = $data['album']['covers'];

        if ($min && count($covers) < $min) {
            if (isset($data['albums'])) {
                $pool = [];
                foreach ($data['albums'] as $album) {
                    if (isset($album['covers']) && count($album['covers'])) {
                        $pool = array_merge($pool, $album['covers']);
                    }
                }
            } elseif (isset($data['content'])) {
                $pool = $data['content'];
            } elseif ($data['album_type'] === 'standard') {
                $diff = $min - count($covers);
                $content = self::api(
                    '/albums/' . $data['album']['id'] .
                    '/content/covers:0/with_covers:0/with_context:0/limit:' . $diff .
                    (isset($data['album']['__cover_hint_before']) ? '/order_by:published_on/before:' . $data['album']['__cover_hint_before'] : '')
                );
                $pool = $content['content'];
            } else {
                $pool = [];
            }

            if (count($pool) && $min - count($covers) > 0) {
                $ids = [];
                foreach ($covers as $c) {
                    $ids[] = $c['id'];
                }

                foreach ($pool as $content) {
                    if (isset($content['id']) && !in_array($content['id'], $ids)) {
                        $covers[] = $content;
                    }

                    if (count($covers) >= $min) {
                        break;
                    }
                }
            }
        }

        if ($max) {
            $covers = array_slice($covers, 0, $max);
        }

        return $covers;
    }

    private static function parse_source_aliases($source)
    {
        $aliases = array(
            'date' => 'events',
            'archives' => 'events',
            'timeline' => 'events'
        );

        if (isset($aliases[$source])) {
            return $aliases[$source];
        }

        return $source;
    }

    public static function load($params)
    {
        $defaults = array(
            'model' 			=> 'content',
            'list'				=> false,
            'filters'			=> array(),
            'id_from_url' 		=> false,
            'paginate_from_url' => false,
            'api'				=> array(),
            'load_content'		=> false,
            'id'				=> false,
            'id_prefix'			=> '',
            'tree' 				=> false,
            'type' 				=> false,
            'source'			=> false,
            'archive'			=> false
        );

        $featured = false;

        $params['filters'] = [];

        foreach ($params as $key => $val) {
            if (strpos($key, 'filter:') === 0) {
                $left = str_replace('filter:', '', $key);
                if (strpos($left, ':not') === false) {
                    $right = '';
                } else {
                    $right = '!';
                    $left = str_replace(':not', '', $left);
                }
                $params['filters'][] = $left . '=' . $right . $val;
            } elseif (!isset($defaults[$key])) {
                $defaults['api'][$key] = $val;
            }
        }

        $custom = false;

        if (isset($params['source'])) {
            $params['source'] = self::parse_source_aliases($params['source']);
            $source = array('type' => $params['source']);
            $defaults['list'] = substr(strrev($params['source']), 0, 1) === 's';
            $defaults['model'] = rtrim($params['source'], 's') . 's';
            $defaults['filters'] = isset($params['filters']) ? $params['filters'] : array();
            $custom = true;
            if (isset($params['tree'])) {
                $defaults['tree'] = true;
            }
        } elseif (Koken::$source) {
            Koken::$source['type'] = self::parse_source_aliases(Koken::$source['type']);
            $source = Koken::$source;
            $defaults['list'] = substr(strrev(Koken::$source['type']), 0, 1) === 's';
            $defaults['model'] = rtrim(Koken::$source['type'], 's') . 's';
            $defaults['filters'] = is_array(Koken::$source['filters']) ? Koken::$source['filters'] : array();
        }

        if ($defaults['model'] === 'events' && $defaults['list']) {
            $defaults['api']['load_items'] = 1;
        }

        if ($defaults['model'] === 'sets' && $defaults['list']) {
            $defaults['model'] = 'albums';
            $defaults['api']['types'] = 'set';
        }

        if (strpos($defaults['model'], 'featured_') === 0) {
            $bits = explode('_', $defaults['model']);
            $defaults['model'] = 'features';
            $defaults['id'] = rtrim($bits[1], 's');
            if ($defaults['id'] === 'essay') {
                $defaults['id'] = 'text';
            } elseif ($defaults['id'] === 'album') {
                $defaults['api']['include_empty'] = 0;
            }

            $defaults['list'] = true;
        }

        if ($defaults['model'] === 'contents') {
            $defaults['model'] = 'content';
        } elseif ($defaults['model'] === 'essays' || $defaults['model'] === 'pages') {
            $defaults['api']['type'] = trim($defaults['model'], 's');
            $defaults['model'] = 'text';
        } elseif ($defaults['model'] === 'categorys') {
            $defaults['model'] = 'categories';
        } elseif ($defaults['model'] === 'sets') {
            $defaults['model'] = 'albums';
        }

        if (is_array($defaults['filters'])) {
            $customs = [];

            $defaults['filters'] = array_filter($defaults['filters'], function ($filter) use (&$customs) {
                if (isset(Shutter::$custom_sources[$filter])) {
                    $customs = array_merge($customs, Shutter::$custom_sources[$filter]['filters']);
                    return false;
                }

                return true;
            });

            $defaults['filters'] = array_merge($defaults['filters'], $customs);

            foreach ($defaults['filters'] as $filter) {
                if (strpos($filter, '=') !== false) {
                    $bits = explode('=', $filter);
                    if ($bits[0] === 'id' && $bits[1][0] !== '!') {
                        $__id = substr($bits[1], 0, 1) === '"' ? $bits[1] : urlencode($bits[1]);
                    } elseif ($bits[0] === 'members') {
                        $params['type'] = $bits[1];
                    } else {
                        if (strpos($bits[1], '!') === 0 || strpos($bits[0], '!') !== false) {
                            $bits[1] = str_replace('!', '', $bits[1]);
                            $bits[0] = str_replace('!', '', $bits[0]) . '_not';
                        }
                        if (strpos($bits[0], 'category') === 0 && (!is_numeric($bits[1]) && strpos($bits[1], '" . Koken') !== 0)) {
                            $bits[1] = Koken::$categories[strtolower($bits[1])];
                        }
                        $defaults['api'][$bits[0]] = $bits[1];
                    }
                } else {
                    if (substr($filter, 0, 1) === '!') {
                        $filter = str_replace('!', '', $filter);
                        $val = 0;
                    } else {
                        $val = 1;
                    }

                    if ($filter === 'featured' && $val === 1) {
                        $featured = true;
                    } else {
                        $defaults['api'][$filter] = $val;
                    }
                }
            }
        }

        if ($source['type'] === 'tags' && isset($__id)) {
            $defaults['id'] = $__id;
        } elseif ($source['type'] === 'event' && isset(Koken::$routed_variables['year'])) {
            $__id = implode('-', array(Koken::$routed_variables['year'], Koken::$routed_variables['month'], Koken::$routed_variables['day']));
        } elseif ($source['type'] === 'tag' && isset($defaults['api']['tag'])) {
            $__id = $defaults['api']['tag'];
            unset($defaults['api']['tag']);
        }

        if ($source['type'] === 'events' && !$custom) {
            if (isset(Koken::$routed_variables['month'])) {
                $defaults['api']['month'] = Koken::$routed_variables['month'];
            } elseif (isset($defaults['api']['month'])) {
                Koken::$routed_variables['month'] = $defaults['api']['month'];
            }
            if (isset(Koken::$routed_variables['year'])) {
                $defaults['api']['year'] = Koken::$routed_variables['year'];
            } elseif (isset($defaults['api']['year'])) {
                Koken::$routed_variables['year'] = $defaults['api']['year'];
            }
            if (isset(Koken::$routed_variables['day'])) {
                $defaults['api']['day'] = Koken::$routed_variables['day'];
            } elseif (isset($defaults['api']['day'])) {
                Koken::$routed_variables['day'] = $defaults['api']['day'];
            }
        }

        if ($source['type'] === 'archive' && !$custom) {
            if ($params['type'] === 'essays') {
                $defaults['api']['type'] = 'essay';
                $defaults['model'] = 'text';
            } else {
                $defaults['model'] = $params['type'] === 'contents' ? 'content' : $params['type'];
            }
            $defaults['list'] = true;
            if (isset(Koken::$routed_variables['month'])) {
                $defaults['api']['month'] = Koken::$routed_variables['month'];
            } elseif (isset($defaults['api']['month'])) {
                Koken::$routed_variables['month'] = $defaults['api']['month'];
            }
            if (isset(Koken::$routed_variables['year'])) {
                $defaults['api']['year'] = Koken::$routed_variables['year'];
            } elseif (isset($defaults['api']['year'])) {
                Koken::$routed_variables['year'] = $defaults['api']['year'];
            }
            if (isset(Koken::$routed_variables['day'])) {
                $defaults['api']['day'] = Koken::$routed_variables['day'];
            } elseif (isset($defaults['api']['day'])) {
                Koken::$routed_variables['day'] = $defaults['api']['day'];
            }

            $defaults['archive'] = 'date';
        }

        if (!$defaults['list']) {
            if ($source['type'] === 'tag' && !$custom && isset($params['type'])) {
                if ($params['type'] === 'essays') {
                    $defaults['api']['type'] = 'essay';
                    $defaults['model'] = 'text';
                    $defaults['api'] = array_merge(array('order_by' => 'published_on', 'state' => 'published'), $defaults['api']);
                } else {
                    $defaults['model'] = $params['type'] === 'contents' ? 'content' : $params['type'];
                }
                $defaults['list'] = true;
                $defaults['id_prefix'] = 'tags:';
                $defaults['paginate_from_url'] = true;
                $defaults['archive'] = 'tag';
            }

            if (isset($__id)) {
                $defaults['id'] = $__id;
                if (!$custom) {
                    Koken::$routed_variables['id'] = $__id;
                }

                if ($source['type'] === 'tag') {
                    $defaults['id_prefix'] = 'slug:';
                }
            } else {
                $defaults['id_from_url'] = true;
            }

            if (in_array($defaults['model'], array('albums', 'events', 'tags', 'categories'))) {
                $defaults['list'] = true;
                $defaults['paginate_from_url'] = true;
            }
        }

        if ($defaults['list']) {
            if ($defaults['model'] === 'albums' || $defaults['model'] === 'categories') {
                $defaults['api'] = array_merge(array('include_empty' => '0'), $defaults['api']);
            } elseif ($defaults['model'] === 'text') {
                $defaults['api'] = array_merge(array('order_by' => 'published_on', 'state' => 'published'), $defaults['api']);
            }

            $defaults['paginate_from_url'] = true;
        }

        $options = $defaults;

        $paginate = $options['paginate_from_url'] && $options['list'] && !$custom;

        $url = '/' . $options['model'] . ($featured ? '/featured' : '');

        if ($options['tree'] && $options['model'] === 'albums') {
            $url .= '/tree';
        }

        if ($options['model'] === 'text' && isset($_GET['preview_draft'])) {
            $options['id'] = $_GET['preview_draft'];
            $options['id_from_url'] = false;
        }

        if ($options['list'] && isset($__id) && !$options['id']) {
            $url .= '/' . urldecode($__id);
            if (strpos(urldecode($__id), ',') === false) {
                $options['list'] = false;
            }
        }

        if ($options['id_from_url'] || $options['id']) {
            if (!isset($defaults['api']['custom'])) {
                if ($options['id_from_url']) {
                    if (empty($options['id_prefix'])) {
                        $slug_prefix = 'slug:';
                    } else {
                        $slug_prefix = '';
                    }
                    if (($options['id_prefix'] === 'tags:' || $options['model'] === 'tags') && preg_match('/tag\-\d+/', self::$routed_variables['slug'])) {
                        self::$routed_variables['slug'] = str_replace('tag-', '', self::$routed_variables['slug']);
                    }
                    $url .= "/{$options['id_prefix']}" . (isset(self::$routed_variables['id']) ? urlencode(self::$routed_variables['id']) : $slug_prefix . urlencode(self::$routed_variables['slug']));
                } elseif ($options['id']) {
                    $url .= "/{$options['id_prefix']}" . urlencode($options['id']);
                }

                if (!isset($defaults['api']['context'])) {
                    if ($options['model'] === 'content') {
                        $url .= self::context_parameters();
                    } elseif ($options['model'] === 'text') {
                        $url .= self::context_parameters('essay');
                    } elseif ($options['model'] === 'albums' && $options['list']) {
                        $url .= '/content' . Koken::context_parameters('album');
                    }
                }
            }
        }

        if (isset($params['type']) && $options['model'] === 'categories') {
            $url .= '/' . ($params['type'] === 'contents' ? 'content' : $params['type']);
            $options['list'] = true;
            $paginate = !$custom;
            $options['archive'] = 'category';
            if ($params['type'] === 'essays') {
                $options['api'] = array_merge(array('order_by' => 'published_on', 'state' => 'published'), $defaults['api']);
            }
        }

        if (in_array($options['model'], array('content', 'text', 'albums'))) {
            if (!isset($options['api']['neighbors'])) {
                $options['api']['neighbors'] = 2;
            }

            $max_n = 2;
            foreach (self::$max_neighbors as $max) {
                if (!is_numeric($max)) {
                    preg_match('/settings\.([a-z_]+)/', $max, $match);
                    if ($match) {
                        $max = self::$settings[$match[1]];
                    }
                }
                $max_n = max($max_n, $max);
            }
            $options['api']['neighbors'] = max($options['api']['neighbors'], $max_n);
        }

        if (!$custom) {
            $overrides = Koken::$location['parameters']['__overrides'];
            if (isset($overrides['order_by']) && strpos($overrides['order_by'], '_on') !== false && !isset($overrides['order_direction'])) {
                $overrides['order_direction'] = 'desc';
            }
            $options['api'] = array_merge($options['api'], $overrides);
        }

        foreach ($options['api'] as $key => $value) {
            if (!is_numeric($value) && $value == 'true') {
                $value = 1;
            } elseif (!is_numeric($value) && $value == 'false') {
                $value = 0;
            } else {
                $value = urlencode($value);
            }
            $url .= "/$key:$value";
        }

        $collection_name = $options['model'] === 'contents' || ($options['model'] === 'albums' && ($options['id_from_url'] || $options['id']) && $options['list']) ? 'content' : $options['model'];

        return array(
            $url, $options, $collection_name, $paginate
        );
    }

    public static function time($token, $options, $attr)
    {
        $options['relative'] = $options['relative'] === 'true' || $options['relative'] == '1';
        $has_class = $options['class'] !== 'false';
        $options['rss'] = $options['rss'] !== 'false';

        if (!$token) {
            $token = array(
                'timestamp' => time(),
                'utc' => true,
            );
        } elseif (!isset($token['timestamp'])) {
            if (isset($token['album'])) {
                $token = $token['album']['date'];
            } elseif (isset($token['date']) && isset($token['date']['timestamp'])) {
                $token = $token['date'];
            } elseif (isset($token['event']) || (isset($token['__koken__']) && $token['__koken__'] === 'event') || isset($token['year'])) {
                $obj = isset($token['event']) ? $token['event'] : $token;
                $str = $obj['year'] . '-';
                $format = 'Y';

                if (isset($obj['month'])) {
                    $str .= $obj['month'] . '-';
                    $format = 'F ' . $format;

                    if (isset($obj['day'])) {
                        $str .= $obj['day'];
                        $format = 'date';
                    } else {
                        $str .= '01';
                    }
                } else {
                    $str .= '01-01';
                }
                $token = array(
                    'timestamp' => strtotime($str),
                    'utc' => true,
                );
                $options['show'] = $format;
            } elseif (isset($token['year'])) {
                $m = isset($token['month']) ? $token['month'] : 1;
                $d = isset($token['day']) ? $token['day'] : 1;
                $token = array(
                    'timestamp' => strtotime($token['year'] . '-' . $m . '-' . $d),
                    'utc' => true,
                );
                $options['show'] = 'F Y';
            }
        }

        $timestamp = $token['timestamp'];

        // Don't timeshift dates where UTC param is false
        // Assume these dates are hardwired, like captured_on
        if (isset($token['utc']) && !$token['utc']) {
            date_default_timezone_set('UTC');
        }

        if ($options['rss']) {
            echo(date('D, d M Y H:i:s O', $timestamp));
        } else {
            if ($has_class) {
                $klass = $options['class'];
            } else {
                $klass = '';
            }
            if ($options['relative']) {
                $klass .= ' k-relative-time';
            }

            if (!empty($klass)) {
                $klass = ' class="' . $klass . '"';
            }

            switch ($options['show']) {
                case 'both':
                    $f = self::$site['date_format'] . ' ' . self::$site['time_format'];
                    break;

                case 'date':
                    $f = self::$site['date_format'];
                    break;

                case 'time':
                    $f = self::$site['time_format'];
                    break;

                default:
                    $f = $options['show'];
                    break;
            }

            $dt = date('c', $timestamp);

            if (class_exists('IntlDateFormatter') && isset(self::$settings['language']) && self::$settings['language'] !== 'en') {
                $df = new IntlDateFormatter(self::$settings['language'], IntlDateFormatter::NONE, IntlDateFormatter::NONE);
                $df->setPattern(self::to_date_field_symbol($f));

                $text = $df->format(new DateTime($dt));
            } else {
                $text = date($f, $timestamp);
            }

            $attr_clean = [];

            foreach ($attr as $key => $val) {
                $attr_clean[] = "$key=\"$val\"";
            }
            $attr = implode(' ', $attr_clean);
            echo <<<OUT
<time{$klass} datetime="$dt" $attr>
	$text
</time>
OUT;
        }
        date_default_timezone_set(Koken::$site['timezone']);
    }

    private static function get_event_date($obj)
    {
        if (isset($obj['published_on'])) {
            return $obj['published_on']['timestamp'];
        } elseif (isset($obj['created_on'])) {
            return $obj['created_on']['timestamp'];
        } elseif (isset($obj['uploaded_on'])) {
            return $obj['uploaded_on']['timestamp'];
        }
    }

    public static function event_sort($one, $two)
    {
        $one_d = self::get_event_date($one);
        $two_d = self::get_event_date($two);

        return $one_d < $two_d ? 1 : -1;
    }

    public static function start()
    {
        self::$start_time = microtime(true);

        self::$messages = Shutter::get_messages();
    }

    public static function cleanup()
    {
        $overall = round((microtime(true) - self::$start_time)*1000);

        if (self::$curl_handle) {
            curl_close(self::$curl_handle);
            self::$curl_handle = false;
        }

        if (error_reporting() !== 0 && !self::$rss) {
            arsort(self::$timers);

            $t = [];
            $total = 0;

            $total = array_sum(self::$timers);

            foreach (self::$timers as $url => $time) {
                $p = round(($time / $total) * 100);
                $time = round($time*1000);
                $t[] = "{$time}ms ($p%)\n\t----------------------------------\n\t$url";
            }

            $total = round($total*1000);
            return "<!--\n\n\tKOKEN DEBUGGING (Longest requests first)\n\n\t" . join("\n\n\t", $t) . "\n\n\tTotal API calls: " . count(self::$timers) . "\n\tTotal API time: {$total}ms\n\tTotal time: {$overall}ms\n-->";
        }
    }

    private static function fallback_load($base, $params)
    {
        foreach ($params as $key => $val) {
            if ($key === 'limit_to' || $key === 'order_by' || strpos($key, 'filter:') === 0) {
                $base .= '/' . str_replace('filter:', '', $key) . ':' . $val;
            }
        }
        return self::api($base);
    }


    public static function sort_tags_by_count($a, $b)
    {
        if ($a['counts']['total'] > $b['counts']['total'] || ($a['counts']['total'] === $b['counts']['total'] && $a['title'] < $b['title'])) {
            return -1;
        }
        return 1;
    }

    public static function sort_tags_by_count_asc($a, $b)
    {
        if ($a['counts']['total'] < $b['counts']['total'] || ($a['counts']['total'] === $b['counts']['total'] && $a['title'] < $b['title'])) {
            return -1;
        }
        return 1;
    }

    public static function albums($params)
    {
        $params = array_merge(array('filter:include_empty' => 0), $params);
        return self::fallback_load('/albums', $params);
    }

    public static function content($params)
    {
        return self::fallback_load('/content', $params);
    }

    public static function essays($params)
    {
        return self::fallback_load('/text/page_type:essay/published:1', $params);
    }

    public static function tags($params)
    {
        return self::fallback_load('/tags', $params);
    }

    public static function categories($params)
    {
        $params = array_merge(array('filter:include_empty' => 0), $params);
        return self::fallback_load('/categories', $params);
    }

    public static function dates($params)
    {
        return self::fallback_load('/events', $params);
    }
}
