<?php

class Sites extends Koken_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function _clean_value($type, $value)
    {
        if ($type === 'boolean' && is_string($value) && ($value === 'true' || $value === 'false')) {
            $value = $value === 'true';
        } elseif ($type === 'color' && preg_match('/#[a-z0-9]{3}$/i', $value)) {
            $value = $value . substr($value, 1);
        }
        return $value;
    }

    public function _setup_option($key, $option, $data, $scope, $default_style_vars, $style_vars, $send_as = false)
    {
        $_t = [];
        $_t['key'] = $key;

        foreach ($option as $name => $val) {
            if ($name === 'settings' && is_string($val[0])) {
                $_o = [];
                foreach ($val as $v) {
                    $_o[] = array('label' => $v, 'value' => $v);
                }
                $val = $_o;
            }
            if ($name === 'type') {
                $val = strtolower($val);
            }
            $_t[$name] = $val;
        }

        if (isset($_t['value'])) {
            $_t['default'] = $_t['value'];
        } elseif (isset($default_style_vars[$key])) {
            $_t['default'] = $default_style_vars[$key];
        }

        if ($send_as && isset($data[$send_as])) {
            $_t['value'] = $data[$send_as];
        } elseif (isset($data[$key])) {
            $_t['value'] = $data[$key];
        } elseif (isset($style_vars[$key])) {
            $_t['value'] = $style_vars[$key];
        }

        if ($send_as) {
            $_t['send_as'] = $send_as;
        }

        if (!isset($_t['scope']) && $scope) {
            $_t['scope'] = $scope;
        }

        $_t['value'] = $this->_clean_value($_t['type'], $_t['value']);

        if (isset($_t['default'])) {
            $_t['default'] = $this->_clean_value($_t['type'], $_t['default']);
        }

        return $_t;
    }

    public function _prep_options($options, $data = array(), $style_vars = array(), $default_style_vars = array(), $scope = false)
    {
        $_options = $flat = [];

        if (isset($options)) {
            foreach ($options as $group => $opts) {
                $tmp = array( 'group' => $group, 'settings' => array() );

                if (isset($opts['collapse']) && $opts['collapse']) {
                    $tmp['collapse'] = true;
                    unset($opts['collapse']);
                }

                if (isset($opts['icon'])) {
                    $tmp['icon'] = $opts['icon'];
                    $loop = $opts['settings'];
                    if (isset($opts['dependencies'])) {
                        $tmp['dependencies'] = $opts['dependencies'];
                    }
                    if (isset($opts['scope'])) {
                        $scope = $opts['scope'];
                    } else {
                        $scope = false;
                    }
                } else {
                    $loop = $opts;
                }

                foreach ($loop as $key => $arr) {
                    if (isset($arr['value']) && (is_array($arr['value']) || isset($arr['scoped_values']))) {
                        $groups = [];

                        if (is_array($arr['value'])) {
                            $v = [];
                            foreach ($arr['value'] as $_key => $val) {
                                if (strpos($_key, ',') === false) {
                                    $v[$_key] = $val;
                                } else {
                                    $keys = explode(',', $_key);
                                    foreach ($keys as $_k) {
                                        $v[$_k] = $val;
                                        $groups[$_k] = $key . '_' . $_key;
                                    }
                                }
                            }
                            $arr['value'] = $v;
                        }

                        // Internal handling for scoped settings with unique vals
                        foreach ($arr['scope'] as $template) {
                            $copy = $arr;
                            $copy['value'] = is_array($arr['value']) ? $arr['value'][$template] : $arr['value'];
                            $copy['scope'] = array($template);
                            $_key = '__scoped_' . str_replace('.', '-', $template) . '_' . $key;
                            if (isset($groups[$template])) {
                                $send_as = $groups[$template];
                            } else {
                                $send_as = false;
                            }
                            $_t = $this->_setup_option($_key, $copy, $data, $scope, $default_style_vars, $style_vars, $send_as);
                            $tmp['settings'][] = $_t;
                            unset($_t['key']);
                            $flat[$_key] = $_t;
                        }
                    } elseif (isset($arr['label'])) {
                        $_t = $this->_setup_option($key, $arr, $data, $scope, $default_style_vars, $style_vars);
                        $tmp['settings'][] = $_t;
                        unset($_t['key']);
                        $flat[$key] = $_t;
                    } else {
                        list($sub, $_flat) = $this->_prep_options(array($key => $arr), $data, $style_vars, $default_style_vars, $scope);
                        $tmp['settings'][] = array_pop($sub);
                        $flat = array_merge($flat, $_flat);
                    }
                }

                $_options[] = $tmp;
            }
        }

        return array( $_options, $flat );
    }

    public function set_order()
    {
        $url = new Url();
        $current = $url->order_by('id DESC')->limit(1)->get();

        $data = unserialize($current->data);

        foreach ($data as &$config) {
            if ($config['type'] === $this->input->post('type')) {
                $config['data']['order'] = $this->input->post('order');
                break;
            }
        }

        $current->data = serialize($data);
        $current->save();

        exit;
    }

    public function index()
    {
       [$params, $id] = $this->parse_params(func_get_args());
        $site = new Setting();
        $site->like('name', 'site_%')->or_like('name', 'image_%')->get_iterated();

        $draft = new Draft();
        $data = [];
        $ds = DIRECTORY_SEPARATOR;
        $template_path = FCPATH . 'storage' . $ds . 'themes' . $ds;
        $defaults = json_decode(file_get_contents(FCPATH . 'app' . $ds . 'site' . $ds . 'defaults.json'), true);
        $default_template_path = FCPATH . 'app' . $ds . 'site' . $ds . 'themes' . $ds;
        $pulse_base = FCPATH . 'app' . $ds . 'site' . $ds . 'themes' . $ds . 'common' . $ds . 'js' . $ds . 'pulse.json';

        $user = new User();
        $user->get();

        if (isset($params['preview'])) {
            $theme_root = $template_path . $params['preview'] . $ds;
            $template_info = json_decode(file_get_contents($theme_root . 'info.json'), true);
            if (!$template_info) {
                $this->set_response_data(array( 'error' => 'Unable to parse the info.json file for this theme.'));
                return;
            }

            $p = new Draft();
            $p->path = $params['preview'];
            $p->init_draft_nav();
            $draft->data = json_decode($p->data, true);
        } else {
            if (isset($params['draft'])) {
                $draft->where('draft', 1);
            } else {
                $draft->where('current', 1);
            }

            $draft->get();

            if ($draft->exists()) {
                $theme_root = $template_path . $draft->path . $ds;
                $template_info = json_decode(file_get_contents($theme_root . 'info.json'), true);

                if (!$template_info) {
                    $this->set_response_data(array( 'error' => 'Unable to parse the info.json file for this theme.'));
                    return;
                }

                $is_live = $draft->current && $draft->data === $draft->live_data;

                $template_info['published'] = $is_live;

                $draft->data = json_decode(isset($params['draft']) ? $draft->data : $draft->live_data, true);
            } else {
                $this->error('404', 'Draft not found.');
                return;
            }
        }

        foreach ($defaults['templates'] as $path => $info) {
            if (!file_exists($theme_root . $path . '.lens') && !file_exists($default_template_path . $path . '.lens')) {
                unset($defaults['templates'][$path]);
            }
        }

        foreach ($defaults['routes'] as $url => $info) {
            if (!isset($defaults['templates'][$info['template']])) {
                unset($defaults['routes'][$url]);
            }
        }

        if (isset($template_info['routes'])) {
            $template_info['routes'] = array_merge_custom($defaults['routes'], $template_info['routes']);
        } else {
            $template_info['routes'] = $defaults['routes'];
        }

        if (isset($template_info['templates'])) {
            $template_info['templates'] = array_merge_custom($defaults['templates'], $template_info['templates']);
        } else {
            $template_info['templates'] = $defaults['templates'];
        }

        if (isset($template_info['language'])) {
            $template_info['language'] = array_merge_custom($defaults['language'], $template_info['language']);
        } else {
            $template_info['language'] = $defaults['language'];
        }

        $files = scandir($theme_root);

        foreach ($files as $file) {
            $info = pathinfo($file);
            if (isset($info['extension']) && $info['extension'] === 'lens' && $info['filename'] !== 'error' && !isset($template_info['templates'][$info['filename']])) {
                $template_info['templates'][$info['filename']] = array(
                    'name' => ucfirst(preg_replace('/[^a-z0-9]/', ' ', strtolower($info['filename'])))
                );
            }
        }
        if (isset($template_info['styles'])) {
            if (isset($draft->data['settings']['__style']) && isset($template_info['styles'][$draft->data['settings']['__style']])) {
                $key = $draft->data['settings']['__style'];
            } else {
                $keys = array_keys($template_info['styles']);
                $key = $draft->data['settings']['__style'] = array_shift($keys);
            }

            $template_info['style'] = array_merge(array('key' => $key), $template_info['styles'][$key]);

            $styles = [];

            foreach ($template_info['styles'] as $key => $opts) {
                $styles[] = array_merge(array('key' => $key), $opts);
            }

            $template_info['styles'] = $styles;
        } else {
            $template_info['styles'] = [];
        }

        if ($this->method == 'get') {
            list($data['urls'], $data['url_data'], $routes) = $draft->setup_urls($theme_root);

            if (isset($params['draft'])) {
                function get_live_updates($file, $draft, &$functions)
                {
                    if (file_exists($file)) {
                        // Strip comments so they don't confuse the parser
                        $contents = preg_replace('/\/\*.*?\*\//si', '', file_get_contents($file));

                        preg_match_all('/@import\surl\(.*\[?\$([a-z_0-9]+)\]?.*\);/', $contents, $imports);

                        foreach ($imports[1] as $setting) {
                            if (!isset($functions[$setting])) {
                                $functions[$setting] = 'reload';
                            }
                        }

                        $contents = preg_replace('/@import\surl\(.*\);/', '', $contents);

                        preg_match_all('/([^\{]+)\s*\{([^\}]+)\}/s', $contents, $matches);

                        foreach ($matches[2] as $index => $block) {
                            $selector = $matches[1][$index];
                            preg_match_all('/([a-z\-]+):([^;]+)( !important)?;/', $block, $rules);

                            foreach ($rules[2] as $j => $rule) {
                                $property = $rules[1][$j];

                                preg_match_all('/\[?\$([a-z_0-9]+)\]?/', $rule, $options);

                                if (count($options)) {
                                    foreach ($options[1] as $option) {
                                        if (!isset($functions[$option])) {
                                            $functions[$option] = [];
                                        } elseif ($functions[$option] === 'reload') {
                                            continue;
                                        }
                                        $functions[$option][] = array(
                                            'selector' => trim(str_replace("\n", '', $selector)),
                                            'property' => trim($property),
                                            'template' => trim(str_replace('url(', "url(storage/themes/{$draft->path}/", $rule)),
                                            'lightbox' => strpos($file, 'lightbox-settings.css.lens') !== false,
                                        );
                                    }
                                }
                            }
                        }
                    }
                }

                $functions = [];
                get_live_updates(FCPATH . $ds . 'storage' . $ds . 'themes' . $ds . $draft->path . $ds . 'css' . $ds . 'settings.css.lens', $draft, $functions);
                get_live_updates(FCPATH . $ds . 'storage' . $ds . 'themes' . $ds . $draft->path . $ds . 'css' . $ds . 'lightbox-settings.css.lens', $draft, $functions);
                $template_info['live_updates'] = $functions;
            }

            $pulse_settings = json_decode(file_get_contents($pulse_base), true);

            list($template_info['pulse'], $template_info['pulse_flat']) = $this->_prep_options($pulse_settings);

            if (isset($draft->data['pulse_groups'])) {
                $template_info['pulse_groups'] = $draft->data['pulse_groups'];
                foreach ($template_info['pulse_groups'] as &$group) {
                    if (isset($group['transition_duration']) && is_numeric($group['transition_duration']) && $group['transition_duration'] > 10) {
                        $group['transition_duration'] /= 1000;
                    }
                }
            } else {
                $template_info['pulse_groups'] = [];
            }

            if (!isset($template_info['templates'])) {
                $template_info['templates'] = [];
            }

            if (!isset($template_info['routes'])) {
                $template_info['routes'] = [];
            }

            if (isset($draft->data['routes'])) {
                $template_info['routes'] = array_merge_custom($template_info['routes'], $draft->data['routes']);
            }

            $template_info['navigation'] = $draft->data['navigation'];

            unset($template_info['navigation_groups']);

            $albums_flat = new Album();
            $albums_flat
                ->select('id,level,left_id')
                ->where('deleted', 0)
                ->order_by('left_id ASC')
                ->get_iterated();

            $albums_indexed = [];
            $ceiling = 1;
            foreach ($albums_flat as $a) {
                $albums_indexed[$a->id] = array('level' => (int) $a->level);
                $ceiling = max($a->level, $ceiling);
            }

            $album_keys = array_keys($albums_indexed);

            function nest($nav, $routes, $albums_indexed, $album_keys, $ceiling)
            {
                $l = 1;

                $nested = [];

                while ($l <= $ceiling) {
                    foreach ($nav as $index => $item) {
                        if (preg_match('/^(mailto|https?)/', $item['path']) || (!isset($item['auto']) && !isset($routes[$item['path']]))) {
                            if ($l === 1) {
                                $nested[] = $item;
                            }
                            continue;
                        }
                        if (isset($routes[$item['path']])) {
                            $r = $routes[$item['path']];
                        } else {
                            $r = false;
                        }
                        if ((isset($item['auto']) && in_array($item['auto'], array('set', 'album'))) || ($r && isset($r['source']) && in_array($r['source'], array('set', 'album')))) {
                            if (isset($item['auto'])) {
                                $id = $item['id'];

                                if ($item['auto'] === 'set') {
                                    $item['set'] = true;
                                }
                            } else {
                                foreach ($r['filters'] as $f) {
                                    if (strpos($f, 'id=') === 0) {
                                        $array = explode('=', $f);
                                        $id = array_pop($array);
                                        break;
                                    }
                                }

                                if ($r['source'] === 'set') {
                                    $item['set'] = true;
                                }
                            }

                            if (isset($albums_indexed[$id])) {
                                $level = $albums_indexed[$id]['level'];

                                if ($level === $l && $l === 1) {
                                    $nested[] = $item;
                                    $albums_indexed[$id]['nav'] =& $nested[ count($nested) - 1];
                                    unset($nav[$index]);
                                } elseif ($level === $l) {
                                    while ($level > 0) {
                                        $level--;
                                        $done = false;
                                        $start = array_search($id, $album_keys);
                                        while ($start > 0) {
                                            $start--;
                                            $_id = $album_keys[$start];

                                            if (array_key_exists($_id, $albums_indexed) && $albums_indexed[$_id]['level'] === $level && isset($albums_indexed[$_id]['nav'])) {
                                                $albums_indexed[$_id]['nav']['items'][] = $item;
                                                $albums_indexed[$id]['nav'] =& $albums_indexed[$_id]['nav']['items'][ count($albums_indexed[$_id]['nav']['items']) - 1];
                                                unset($nav[$index]);
                                                $done = true;
                                                break;
                                            }
                                        }
                                        if ($done) {
                                            break;
                                        }
                                    }
                                }
                            }
                        } elseif ($l === 1) {
                            $nested[] = $item;
                            unset($nav[$index]);
                        }
                    }

                    $l++;
                }

                return $nested;
            }

            function build_autos($items, $data, $user, $routes = null, $templates = null)
            {
                foreach ($items as $index => &$item) {
                    if (isset($item['auto'])) {
                        if (isset($data['urls'][$item['auto']])) {
                            $item['path'] = $data['urls'][$item['auto']];
                        } elseif (is_array($routes) &&
                            is_array($templates) &&
                            isset($routes[$item['auto']]) &&
                            isset($templates[$item['auto']]['name'])) {
                            $item['path'] = $routes[$item['auto']];
                            $item['label'] = $templates[$item['auto']]['name'];
                        } elseif ($item['auto'] === 'set' || $item['auto'] === 'custom') {
                            $item['path'] = '';
                        }

                        if ($item['auto'] === 'profile') {
                            switch ($item['id']) {
                                case 'twitter':
                                    $item['path'] = 'https://twitter.com/' . $user->twitter;
                                    break;

                                default:
                                    $item['path'] = $user->{$item['id']};
                                    if (empty($item['path'])) {
                                        unset($items[$index]);
                                        continue;
                                    }
                                    break;
                            }

                            if (!isset($item['label']) || empty($item['label'])) {
                                $item['label'] = ucwords($item['id']) . ($item['id'] === 'google' ? '+' : '');
                            }
                        } elseif ($item['auto'] === 'rss') {
                            $item['path'] = '/feed/' . $item['id'] . ($item['id'] === 'essay' ? 's' : '') . '/recent.rss';
                            if (!isset($item['label'])) {
                                $item['label'] = $data['url_data'][$item['id']]['plural'] . ' RSS';
                            }
                        } elseif (preg_match('/s$/', $item['auto']) || $item['auto'] === 'timeline') {
                            if ($item['auto'] === 'timeline' && isset($item['year'])) {
                                $item['path'] .= $item['year'] . '/';
                                if (isset($item['month']) && $item['month'] !== false && $item['month'] !== 'any') {
                                    $m = str_pad($item['month'], 2, '0', STR_PAD_LEFT);
                                    $item['path'] .= $m . '/';
                                }
                            }

                            if (strpos($item['auto'], '_') !== false) {
                                foreach (array('id', 'slug', 'month', 'year', 'day') as $id) {
                                    if ($id === 'month') {
                                        if (!isset($item['month']) || $item['month'] === 'any' || $item['month'] === false) {
                                            $item['month'] = '';
                                        } else {
                                            $item['month'] = str_pad($item['month'], 2, '0', STR_PAD_LEFT);
                                        }
                                    }
                                    if ($id === 'day' && !isset($item['day'])) {
                                        $item['day'] = '';
                                    }
                                    if ($id === 'slug' && !isset($item['slug']) && isset($item['id'])) {
                                        if (strpos($item['auto'], 'tag_') === 0) {
                                            $item['slug'] = $item['id'];
                                        } else {
                                            $c = new Category();
                                            if (is_numeric($item['id'])) {
                                                $c->select('slug')->get_by_id($item['id']);
                                                $item['slug'] = $c->slug;
                                            } else {
                                                $item['slug'] = $item['id'];
                                            }
                                        }
                                    }
                                    if (isset($item[$id])) {
                                        $item['path'] = str_replace(":$id", $item[$id], $item['path']);
                                    }
                                }
                            } elseif (!isset($item['label'])) {
                                $item['label'] = $data['url_data'][$item['auto'] === 'categories' ? 'category' : rtrim($item['auto'], 's')]['plural'];
                            }
                        } else {
                            if ($item['auto'] === 'home') {
                                if (!isset($item['label'])) {
                                    $item['label'] = $data['url_data']['home'];
                                }
                                $item['path'] = '/home/';
                            } elseif ($item['auto'] === 'album' || $item['auto'] === 'set') {
                                $a = new Album();
                                $a->select('id,slug,created_on,title');

                                if (is_numeric($item['id'])) {
                                    $a->where('id', $item['id']);
                                } else {
                                    $a->where('slug', $item['id'])->or_where('internal_id', $item['id']);
                                }

                                $a->get();

                                if (!$a->exists()) {
                                    unset($items[$index]);
                                    continue;
                                }

                                $item['path'] = str_replace(':id', $a->id, $item['path']);
                                $item['path'] = str_replace(':slug', $a->slug, $item['path']);
                                $item['path'] = str_replace(':year', date('Y', $a->created_on), $item['path']);
                                $item['path'] = str_replace(':month', date('m', $a->created_on), $item['path']);
                                $item['path'] = str_replace(':day', date('d', $a->created_on), $item['path']);
                                if (!isset($item['label'])) {
                                    $item['label'] = $a->title;
                                }
                            } elseif ($item['auto'] === 'page' || $item['auto'] === 'essay') {
                                $t = new Text();
                                $t->select('id,slug,published_on,title');

                                if (is_numeric($item['id'])) {
                                    $t->where('id', $item['id']);
                                } else {
                                    $t->where('slug', $item['id']);
                                }

                                $t->get();

                                if (!$t->exists()) {
                                    unset($items[$index]);
                                    continue;
                                }

                                $item['path'] = str_replace(':id', $t->id, $item['path']);
                                $item['path'] = str_replace(':slug', $t->slug, $item['path']);
                                $item['path'] = str_replace(':year', date('Y', $t->published_on), $item['path']);
                                $item['path'] = str_replace(':month', date('m', $t->published_on), $item['path']);
                                $item['path'] = str_replace(':day', date('d', $t->published_on), $item['path']);
                                if (!isset($item['label'])) {
                                    $item['label'] = $t->title;
                                }
                            } elseif ($item['auto'] === 'content') {
                                $c = new Content();
                                $c->select('id,slug,captured_on,title');

                                if (isset($item['album_id'])) {
                                    $item['path'] = preg_replace('/:(id|slug)/', ':album_$1', $data['urls']['album']) . substr(str_replace(':year/:month/', '', $data['urls']['content']), 1);

                                    $a = new Album();
                                    $a->select('id,slug,created_on,title');

                                    if (is_numeric($item['album_id'])) {
                                        $a->where('id', $item['album_id']);
                                    } else {
                                        $a->where('slug', $item['album_id'])->or_where('internal_id', $item['album_id']);
                                    }

                                    $a->get();

                                    if (!$a->exists()) {
                                        unset($items[$index]);
                                        continue;
                                    }

                                    $item['path'] = str_replace(':album_id', $a->id, $item['path']);
                                    $item['path'] = str_replace(':album_slug', $a->slug, $item['path']);
                                    $date = $a->created_on;
                                } else {
                                    $date = $c->captured_on;
                                }

                                if (is_numeric($item['id'])) {
                                    $c->where('id', $item['id']);
                                } else {
                                    $c->where('slug', $item['id'])->or_where('internal_id', $item['id']);
                                }

                                $c->get();

                                if (!$c->exists()) {
                                    unset($items[$index]);
                                    continue;
                                }

                                $item['path'] = str_replace(':id', $c->id, $item['path']);
                                $item['path'] = str_replace(':slug', $c->slug, $item['path']);
                                $item['path'] = str_replace(':year', date('Y', $date), $item['path']);
                                $item['path'] = str_replace(':month', date('m', $date), $item['path']);
                                $item['path'] = str_replace(':day', date('d', $date), $item['path']);
                                if (!isset($item['label'])) {
                                    $item['label'] = $c->title;
                                }

                                if (isset($item['lightbox']) && $item['lightbox']) {
                                    $item['path'] .= 'lightbox/';
                                }
                            } elseif ($item['auto'] === 'tag') {
                                $item['path'] = str_replace(':slug', $item['id'], $item['path']);
                            }
                        }

                        if ($item['auto'] !== 'profile') {
                            $item['path'] = str_replace(array(':year', ':month'), '', $item['path']);
                            $item['path'] = preg_replace('/[\(\)\?\:]/', '', $item['path']);
                            $item['path'] = preg_replace('~[/]+~', '/', $item['path']);
                        }
                    }
                }

                return $items;
            }

            $template_info['navigation']['items'] = build_autos($template_info['navigation']['items'], $data, $user);

            $template_info['navigation']['items_nested'] = nest($template_info['navigation']['items'], $template_info['routes'], $albums_indexed, $album_keys, $ceiling);

            $template_routes = [];
            foreach ($template_info['routes'] as $index => $route) {
                if (isset($route['template']) && is_string($index)) {
                    $template_routes[$route['template']] = $index;
                }
            }

            foreach ($template_info['navigation']['groups'] as &$group) {
                $group['items'] = build_autos($group['items'], $data, $user, $template_routes, $template_info['templates']);
                $group['items_nested'] = nest($group['items'], $template_info['routes'], $albums_indexed, $album_keys, $ceiling);
            }
            $pages = [];
            $paths = [];

            foreach ($template_info['routes'] as $path => $arr) {
                $pages[] = array_merge(array('path' => (string) $path), $arr);
                $paths[] = $path;
            }

            $template_info['routes'] = $pages;

            if (isset($template_info['settings'])) {
                $default_style_vars = [];
                if (isset($template_info['styles']) && count($template_info['styles'])) {
                    $tmp = array_reverse($template_info['styles']);
                    foreach ($tmp as $style) {
                        if (isset($style['variables'])) {
                            $default_style_vars = array_merge($default_style_vars, $style['variables']);
                        }
                    }
                }

                list($template_info['settings'], $template_info['settings_flat']) = $this->_prep_options(
                    $template_info['settings'],
                    isset($draft->data['settings']) ? $draft->data['settings'] : array(),
                    isset($template_info['style']) && isset($template_info['style']['variables']) ? $template_info['style']['variables'] : array(),
                    $default_style_vars
                );

                if (isset($draft->data['settings']) && isset($draft->data['settings']['__style'])) {
                    $template_info['settings_flat']['__style'] = array('value' => $draft->data['settings']['__style']);
                }
            } else {
                $template_info['settings'] = $template_info['settings_flat'] = [];
            }

            if (isset($template_info['style']) && isset($template_info['style']['variables'])) {
                foreach ($template_info['style']['variables'] as $key => &$varval) {
                    if (preg_match('/#[a-z0-9]{3}$/i', $varval)) {
                        $varval = $varval . substr($varval, 1);
                    }

                    if (!isset($template_info['settings_flat'][$key])) {
                        $template_info['settings_flat'][$key] = array( 'value' => $varval );
                    }
                }
            }

            $types = [];
            $names = [];

            $templates_indexed = $template_info['templates'];

            foreach ($template_info['templates'] as $key => $val) {
                if (isset($val['source']) && $val['source'] === 'date') {
                    $val['source'] = 'archives';
                }

                $types[] = array(
                    'path' => $key,
                    'info' => $val
                );

                $names[] = $val['name'];
            }

            natcasesort($names);

            $final = [];

            foreach ($names as $index => $name) {
                $final[] = $types[$index];
            }

            $template_info['templates'] = $final;
            $bools = array('site_hidpi');
            foreach ($site as $s) {
                $clean_key = preg_replace('/^site_/', '', $s->name);
                if (isset($data[$clean_key])) {
                    continue;
                }

                $val = $s->value;
                if (in_array($s->name, $bools)) {
                    $val = $val == 'true';
                }
                $data[$clean_key] = $val;
            }
            $data['draft_id'] = $draft->id;
            $data['theme'] = array(
                'path' => isset($params['preview']) ? $params['preview'] : $draft->path
            );

            unset($data['id']);

            foreach ($template_info as $key => $val) {
                if (in_array($key, array('name', 'version', 'description', 'demo'))) {
                    $data['theme'][$key] = $val;
                } else {
                    $data[$key] = $val;
                }
            }

            $data['routes'] = array_merge($data['routes'], $routes);

            // templates always need to be after routes
            $templates_tmp = $data['templates'];
            $routes_tmp = $data['routes'];
            unset($data['templates']);
            unset($data['routes']);
            $data['routes'] = $routes_tmp;
            $data['templates'] = Shutter::filter('site.templates', array($templates_tmp));

            $data['profile'] = array(
                'name' => $user->public_display === 'both' ? $user->public_first_name . ' ' . $user->public_last_name : $user->{"public_{$user->public_display}_name"},
                'first' => $user->public_first_name,
                'last' => $user->public_last_name,
                'email' => $user->public_email,
                'twitter' => !empty($user->twitter) ? str_replace('@', '', $user->twitter) : '',
                'facebook' => $user->facebook,
                'google_plus' => $user->google
            );

            if (isset($draft->data['custom_css'])) {
                $data['custom_css'] = $draft->data['custom_css'];
            } else {
                $data['custom_css'] = '';
            }

            $this->set_response_data($data);
        } else {
            switch ($this->method) {
                case 'put':

                    global $raw_input_data;
                    $data = json_decode($raw_input_data['data'], true);

                    if (isset($data['revert'])) {
                        if ($data['revert'] === 'all') {
                            $draft->data = $draft->live_data;
                        } else {
                            unset($draft->data['settings']);
                            $draft->data = json_encode($draft->data);
                        }
                    } else {
                        if (isset($data['custom_css'])) {
                            $draft->data['custom_css'] = $data['custom_css'];
                        }

                        if (isset($data['navigation'])) {
                            unset($data['navigation']['active']);
                            $draft->data['navigation'] = $data['navigation'];
                        }

                        if (isset($data['routes'])) {
                            $pages = [];
                            foreach ($data['routes'] as $p) {
                                if (isset($p['section'])) {
                                    continue;
                                }

                                $key = $p['path'];
                                unset($p['path']);
                                if (!in_array($p, $template_info['routes'])) {
                                    $pages[$key] = $p;
                                }
                            }
                            $draft->data['routes'] = $pages;
                        }

                        if (isset($data['settings_send'])) {
                            foreach ($data['settings_send'] as $key => $val) {
                                $draft->data['settings'][$key] = $val;
                            }
                        }

                        if (isset($data['url_data_send'])) {
                            $source = $data['url_data_send']['source'] === 'categories' ? 'category' : rtrim($data['url_data_send']['source'], 's');
                            $u = new Url();
                            $u->order_by('id DESC')->get();
                            $new_data = unserialize($u->data);
                            foreach ($new_data as &$url_data) {
                                if ($url_data['type'] === $source) {
                                    $url_data['data'][$data['url_data_send']['order']] = $data['url_data_send']['value'];
                                    break;
                                }
                            }
                            $u->data = serialize($new_data);
                            $u->save();
                        }

                        if (isset($data['pulse_settings_send']) && !empty($data['pulse_settings_send'])) {
                            if (!isset($draft->data['pulse_groups'][$data['pulse_settings_group']])) {
                                $draft->data['pulse_groups'][$data['pulse_settings_group']] = [];
                            }

                            foreach ($data['pulse_settings_send'] as $key => $val) {
                                $draft->data['pulse_groups'][$data['pulse_settings_group']][$key] = $val;
                            }
                        }

                        $draft->data = json_encode($draft->data);
                    }

                    $draft->save();

                    $this->redirect("/site/draft:true");
                    break;
            }
        }
    }

    public function publish($draft_id = false)
    {
        if (!$draft_id) {
            $this->error('400', 'Draft ID parameter not present.');
            return;
        }

        if ($this->method === 'post') {
            $draft = new Draft();
            $draft->where('id', $draft_id)->get();

            if ($draft->exists()) {
                $draft->where('current', 1)->update('current', 0);
                $draft->live_data = $draft->data;
                $draft->current = 1;
                $draft->save();

                $guid = FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $draft->path . DIRECTORY_SEPARATOR . 'koken.guid';

                if (file_exists($guid)) {
                    $s = new Setting();
                    $s->where('name', 'uuid')->get();
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, KOKEN_STORE_URL . '/register?uuid=' . $s->value .
                        '&theme=' . trim(file_get_contents($guid)));
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    $r = curl_exec($curl);
                    curl_close($curl);
                }
                exit;
            } else {
                $this->error('404', "Draft not found.");
                return;
            }
        } else {
            $this->error('400', 'This endpoint only accepts tokenized POST requests.');
            return;
        }
    }
}

/* End of file site.php */
/* Location: ./system/application/controllers/site.php */
