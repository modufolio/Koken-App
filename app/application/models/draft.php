<?php

class Draft extends DataMapper
{
    /**
     * Constructor: calls parent constructor
     */
    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    public function _push($item, &$array, $type, $level)
    {
        if (!in_array($item, $array)) {
            if ($level === 1) {
                $array[] = $item;
            } else {
                $array[] = ['path' => $item['path'], 'template' => "redirect:$type"];
            }
        }
    }

    public function setup_urls($template_path)
    {
        $this->load->helper(['url', 'text', 'inflector']);
        $url = new Url();
        $url_list = $url->order_by('id DESC')->get_iterated();
        $urls = $data = $routes = [];
        $level = 0;
        $top_segments = [];

        foreach ($url_list as $config) {
            $config = unserialize($config->data);

            $valid = false;

            foreach ($config as $i => $u) {
                // Sanity check
                if ($u['data'] === 'Home') {
                    continue;
                }
                $valid = strlen((string) $u['data']['singular']) > 0 &&
                         strlen((string) $u['data']['plural']) > 0 &&
                         (!in_array($u['type'], ['content', 'favorite', 'feature', 'album', 'set', 'essay']) || isset($u['data']['order']) && strlen((string) $u['data']['order']) > 0);
            }

            if (!$valid) {
                continue;
            }

            $level++;

            $segments = $_data = [];
            $content_regex = false;

            foreach ($config as $i => $u) {
                if ($u['type'] === 'archive') {
                    unset($config[$i]);
                    continue;
                }
                $d = $u['data'];
                if (is_array($d) && !isset($data[$u['type']])) {
                    $data[$u['type']] = $d;
                }

                $_data[$u['type']] = $d;

                if (!isset($segments[$u['type']]) && isset($d['plural'])) {
                    $segment = strtolower(
                        (string) url_title(
                                        convert_accented_characters($d['plural']),
                                        'dash'
                                    )
                    );

                    if (preg_match('/[^a-z\-]+/', $segment) || empty($segment)) {
                        $segment = $u['type'] === 'content' ? 'content' : plural($u['type']);
                    }

                    $segments[$u['type']] = $segment;
                }

                if ($u['type'] === 'content' && !$content_regex && isset($d['url'])) {
                    $content_regex = !str_contains((string) $d['url'], 'slug') ? ':content_id' : ':content_slug';
                }
            }

            if (!isset($_data['timeline'])) {
                $_data['timeline'] = ['singular' => 'Timeline', 'plural' => 'Timeline'];

                if ($level === 1) {
                    $data['timeline'] = $_data['timeline'];
                }

                array_push($config, ['type' => 'timeline', 'data' => $data['timeline']]);

                $segments['timeline'] = 'timeline';
            }

            if (!isset($_data['feature'])) {
                $_data['feature'] = ['singular' => 'Feature', 'plural' => 'Features'];

                if ($level === 1) {
                    $data['feature'] = $_data['feature'];
                }

                array_push($config, ['type' => 'feature', 'data' => $data['feature']]);

                $segments['feature'] = 'features';
            }

            if ($level === 1) {
                $top_segments = $segments;
            }

            $supported = [];
            $supported_raw = [];
            if (file_exists($template_path . 'archive.contents.lens')) {
                $supported[] = $segments['content'];
                $supported_raw[] = 'contents';
            }
            if (file_exists($template_path . 'archive.essays.lens')) {
                $supported[] = $segments['essay'];
                $supported_raw[] = 'essays';
            }
            if (file_exists($template_path . 'archive.albums.lens')) {
                $supported[] = $segments['album'];
                $supported_raw[] = 'albums';
            }

            foreach ($config as $u) {
                if ($u['type'] === 'home') {
                    if ($level === 1) {
                        $data['home'] = $u['data'];
                    }
                    continue;
                }

                $has_detail = file_exists($template_path . $u['type'] . '.lens');

                $type = $u['type'];
                $type_plural = "{$type}s";
                $source = false;
                $template = '/' . $segments[$type];
                $type_data = $_data[$type === 'set' ? 'album' : $type];

                if (!isset($type_data['url']) && in_array($type, ['category', 'tag'])) {
                    $type_data['url'] = 'slug';
                }

                if ($has_detail && isset($type_data['url'])) {
                    $url = $type_data['url'];
                    if (str_contains((string) $url, 'date')) {
                        $template .= '/:year/:month';
                    }
                    if (str_contains((string) $url, 'slug')) {
                        $template .= '/:slug';
                    } else {
                        $template .= '/:id';
                    }
                }

                if ($has_detail && !isset($urls[$u['type']])) {
                    $urls[$u['type']] = rtrim($template, '/') . '/';
                }

                if ($has_detail && $u['type'] !== 'timeline') {
                    if (!isset($urls[$u['type']])) {
                        $urls[$u['type']] = $template;
                    }

                    if ($u['type'] === 'content' || $u['type'] === 'album') {
                        $template .= '(?P<lightbox>/lightbox)?/';
                    } else {
                        $template .= '/';
                    }

                    $arr = ['path' => $template, 'template' => $type, 'source' => $source, 'vars' => false, 'section' => true];

                    if ($u['type'] === 'album') {
                        if (file_exists($template_path . 'content.lens')) {
                            $album_content_template = str_replace('(?P<lightbox>/lightbox)?', '', $template) . $segments['content'] . '/' . $content_regex . '(?P<lightbox>/lightbox)?/';
                        } else {
                            $album_content_template = str_replace('(?P<lightbox>/lightbox)?', '', $template) . $segments['content'] . '/' . $content_regex . '/(?P<lightbox>lightbox)/';
                        }

                        $album_content_template = str_replace(':id', ':album_id', $album_content_template);
                        $album_content_template = str_replace(':slug', ':album_slug', $album_content_template);
                        $album_content_template = str_replace(':content_id', ':id', $album_content_template);
                        $album_content_template = str_replace(':content_slug', ':slug', $album_content_template);
                        $album_content_route = $arr;
                        $album_content_route['path'] = $album_content_template;
                        $album_content_route['template'] = 'content';
                        $album_content_route['source'] = 'content';
                        $album_content_route['filters'] = [];

                        $this->_push($album_content_route, $routes, $u['type'], $level);
                    } else {
                        $arr['filters'] = false;
                    }

                    $this->_push($arr, $routes, $u['type'], $level);

                    if (in_array($u['type'], ['content', 'album', 'essay'])) {
                        if ($u['type'] === 'content' || $u['type'] === 'album') {
                            $lbox = '(?P<lightbox>/lightbox)?/';
                        } else {
                            $lbox = '/';
                        }

                        if (file_exists($template_path . 'tag.lens')) {
                            $arr['path'] = '/' . $segments['tag'] . '/:tag_slug/' . $segments[$u['type']] . '/';
                        } else {
                            $arr['path'] = '/' . $segments[$u['type']] . '/' . $segments['tag'] . '/:tag_slug/';
                        }
                        $arr['path'] .= (!str_contains((string) $type_data['url'], 'slug') ? ':id' : ':slug') . $lbox;
                        $this->_push($arr, $routes, 'tag_' . $u['type'], $level);

                        if ($level === 1) {
                            $urls['tag_' . $u['type']] = str_replace('(?P<lightbox>/lightbox)?', '', $arr['path']);
                        }

                        if (file_exists($template_path . 'category.lens')) {
                            $arr['path'] = '/' . $segments['category'] . '/:category_slug/' . $segments[$u['type']] . '/';
                        } else {
                            $arr['path'] = '/' . $segments[$u['type']] . '/' . $segments['category'] . '/:category_slug/';
                        }

                        $arr['path'] .= (!str_contains((string) $type_data['url'], 'slug') ? ':id' : ':slug') . $lbox;
                        $this->_push($arr, $routes, 'category_' . $u['type'], $level);

                        if ($level === 1) {
                            $urls['category_' . $u['type']] = str_replace('(?P<lightbox>/lightbox)?', '', $arr['path']);
                        }

                        if ($u['type'] === 'content') {
                            $arr['path'] = '/' . $segments['favorite'] . '/:slug' . $lbox;
                            $this->_push($arr, $routes, 'favorite', $level);

                            if ($level === 1) {
                                $urls['favorite'] = str_replace('(?P<lightbox>/lightbox)?', '', $arr['path']);
                            }

                            $arr['path'] = '/' . $segments['feature'] . '/:slug' . $lbox;
                            $this->_push($arr, $routes, 'feature', $level);

                            if ($level === 1) {
                                $urls['feature'] = str_replace('(?P<lightbox>/lightbox)?', '', $arr['path']);
                            }
                        }
                    }
                } elseif ($u['type'] === 'content') {
                    $arr = ['path' => '/' . $segments['content'] . '/:slug/(?P<lightbox>lightbox)/', 'template' => 'content', 'source' => 'content', 'vars' => false, 'section' => true];

                    $this->_push($arr, $routes, $u['type'], $level);
                }

                if (in_array($u['type'], ['content', 'album', 'essay']) && in_array($type_plural, $supported_raw)) {
                    $archive_template = '/' . $segments[$type] . '/:year(?:/:month(?:/:day)?)?/';

                    if ($level === 1) {
                        $urls['archive_' . $type_plural] = $archive_template;
                    }

                    $parts = explode(' ', strtolower((string) $data[$u['type']]['order']));

                    $filters = ["members=$type_plural", "order_by={$parts[0]}", "order_direction={$parts[1]}"];

                    $this->_push(['path' => $archive_template, 'template' => 'archive.' . $type_plural, 'source' => 'archive', 'filters' => $filters, 'vars' => false, 'section' => true], $routes, 'archive_' . $type_plural, $level);

                    if (file_exists($template_path . 'tag.lens')) {
                        $tag_template = '/' . $segments['tag'] . '/:slug/' . $segments[$type] . '/';
                    } else {
                        $tag_template = '/' . $segments[$type] . '/' . $segments['tag'] . '/:slug/';
                    }

                    if ($level === 1) {
                        $urls['tag_' . $type_plural] = $tag_template;
                    }

                    $this->_push(['path' => $tag_template, 'template' => 'archive.' . $type_plural, 'source' => 'tag', 'filters' => $filters, 'vars' => false, 'section' => true], $routes, 'tag_' . $type_plural, $level);

                    if (file_exists($template_path . 'category.lens')) {
                        $category_template = '/' . $segments['category'] . '/:slug/' . $segments[$type] . '/';
                    } else {
                        $category_template = '/' . $segments[$type] . '/' . $segments['category'] . '/:slug/';
                    }

                    if ($level === 1) {
                        $urls['category_' . $type_plural] = $category_template;
                    }

                    $this->_push(['path' => $category_template, 'template' => 'archive.' . $type_plural, 'source' => 'category', 'filters' => $filters, 'vars' => false, 'section' => true], $routes, 'category_' . $type_plural, $level);

                    if ($level === 1) {
                        $routes[] = ['path' => '/' . $segments[$type] . '/' . $segments['tag'] . '/:slug/', 'template' => 'redirect:' . $tag_template];

                        $routes[] = ['path' => '/' . $segments[$type] . '/' . $segments['category'] . '/:slug/', 'template' => 'redirect:' . $category_template];
                    }
                }

                if (in_array($u['type'], ['category', 'favorite', 'feature', 'tag', 'content', 'album', 'set', 'essay', 'timeline'])) {
                    $type = $u['type'] === 'category' ? 'categories' : ($u['type'] === 'timeline' ? 'timeline' : $type_plural);

                    $has_index = file_exists($template_path . $type . '.lens');

                    if ($has_index) {
                        $index_path = '/' . $segments[$u['type']] . '/';

                        if ($type === 'archives') {
                            $index_path = rtrim($index_path, '/') . '(?:/:year(?:/:month)?)?/';
                        }

                        $arr = ['path' => $index_path, 'template' => $type, 'source' => in_array($type, ['archives', 'tags', 'categories']) ? $type : false, 'vars' => false, 'section' => true];

                        if ($type === 'timeline') {
                            $archive_timeline_template = $index_path . ':year(?:/:month)?/';

                            if ($level === 1) {
                                $urls['archive_timeline'] = $archive_timeline_template;
                            }

                            $archive = ['path' => $index_path . ':year(?:/:month)?/', 'template' => file_exists($template_path . 'date.lens') ? 'date' : 'timeline', 'source' => 'timeline', 'vars' => false, 'section' => true];

                            $this->_push($archive, $routes, $type, $level);

                            $detail = ['path' => '/' . $segments['timeline'] . '/:year/:month/:day/', 'template' => file_exists($template_path . 'date.lens') ? 'date' : 'timeline', 'source' => 'event', 'vars' => false, 'section' => true];

                            $this->_push($detail, $routes, $type, $level);

                            if ($level === 1) {
                                $urls['event_timeline'] = '/' . $segments['timeline'] . '/:year/:month/:day/';
                            }
                        }

                        if (isset($data[$u['type']]['order'])) {
                            $parts = explode(' ', strtolower((string) $data[$u['type'] === 'set' ? 'album' : $u['type']]['order']));
                            $arr['filters'] = ["order_by={$parts[0]}", "order_direction={$parts[1]}"];
                        } else {
                            $arr['filters'] = false;
                        }

                        $this->_push($arr, $routes, $type, $level);

                        if (!isset($urls[$type])) {
                            $urls[$type] = $index_path;
                        }
                    }
                }
            }
        }

        $routes[] = ['path' => '/content/:id/in_album/:album_id(?P<lightbox>/lightbox)?/', 'template' => 'redirect:content'];

        $routes[] = ['path' => '/archives(?:/:year(?:/:month)?)?/', 'template' => 'redirect:timeline'];

        $routes[] = ['path' => '/' . $top_segments['set'] . '/', 'template' => 'redirect:soft:albums'];

        $routes[] = ['path' => '/' . $top_segments['page'] . '/:id/', 'template' => 'redirect:page'];

        if (!isset($data['home'])) {
            $data['home'] = 'Home';
        }

        if (!isset($data['tag']['order'])) {
            $data['tag']['order'] = 'count DESC';
        }

        if (!isset($data['category']['order'])) {
            $data['category']['order'] = 'title ASC';
        }

        if (str_starts_with((string) $data['album']['order'], 'captured_on')) {
            $data['album']['order'] = 'published_on DESC';
        }

        $data['page']['url'] = 'sssslug';

        // Define fallback aliases here. First in, first out.
        $aliases = ['tag_contents' => 'tag', 'tag_albums' => 'tag', 'tag_essays' => 'tag', 'category_contents' => 'category', 'category_albums' => 'category', 'category_essays' => 'category', 'archive_contents' => 'event_timeline', 'archive_essays' => 'event_timeline', 'archive_albums' => 'event_timeline'];

        foreach ($aliases as $check => $fallback) {
            if (isset($urls[$check])) {
                continue;
            }

            if (!is_array($fallback)) {
                $fallback = [$fallback];
            }

            foreach ($fallback as $f) {
                if (isset($urls[$f])) {
                    $urls[$check] = $urls[$f];
                    break;
                }
            }
        }

        return [$urls, $data, $routes, $top_segments];
    }

    public function init_draft_nav($refresh = true)
    {
        if ($refresh === 'nav') {
            $this->data = json_decode($this->data, true);
        } else {
            $this->data = [];
        }

        $ds = DIRECTORY_SEPARATOR;
        $template_path = FCPATH . 'storage' . $ds . 'themes' . $ds;
        $theme_root = $template_path . $this->path . $ds;
        $template_info = json_decode(file_get_contents($theme_root . 'info.json'), true);

        [$urls, $data, $routes] = $this->setup_urls($theme_root);

        $this->data['navigation'] = ['items' => []];
        $this->data['navigation']['groups'] = $groups = [];

        $defaults = ['timeline', 'albums', 'contents', 'essays'];

        $used_autos = [];

        $user = new User();
        $user->get();

        $front = ['auto' => 'home', 'front' => true];

        if (isset($template_info['default_front_page']) && in_array($template_info['default_front_page'], ['timeline', 'albums', 'contents', 'essays', 'archives']) && isset($urls[$template_info['default_front_page']])) {
            $defaults = array_diff($defaults, [$template_info['default_front_page']]);
            $front['path'] = $urls[$template_info['default_front_page']];
            $front['auto'] = $template_info['default_front_page'];
            $used_autos[] = $template_info['default_front_page'];
        } else {
            $front['path'] = '/home/';
        }

        $this->data['navigation']['items'][] = $front;

        foreach ($defaults as $default) {
            if (file_exists($theme_root . $default . '.lens')) {
                $used_autos[] = $default;
                $this->data['navigation']['items'][] = ['auto' => $default];
            }
        }

        if (isset($template_info['navigation_groups'])) {
            foreach ($template_info['navigation_groups'] as $key => $info) {
                $items = [];
                if (isset($info['defaults'])) {
                    foreach ($info['defaults'] as $def) {
                        if (is_array($def)) {
                            $def['path'] = $def['url'];
                            unset($def['url']);
                            $items[] = $def;
                        } else {
                            if ($def === 'front') {
                                $items[] = $front;
                            } else {
                                if (in_array($def, ['twitter', 'facebook', 'gplus'])) {
                                    $def = $def === 'gplus' ? 'google' : $def;
                                    if (!empty($user->{$def})) {
                                        $items[] = ['auto' => 'profile', 'id' => $def];
                                    }
                                } elseif (file_exists($theme_root . $def . '.lens')) {
                                    $items[] = ['auto' => $def];
                                }
                            }
                        }
                    }
                }

                $this->data['navigation']['groups'][] = ['key' => $key, 'label' => $info['label'], 'items' => $items];
            }
        }

        if ($refresh === 'nav') {
            $this->data['routes'] = $routes;
        } else {
            $p = new Draft();
            $p->where('current', 1)->get();
            $pub_data = json_decode($p->live_data, true);
            if(isset($pub_data['navigation']['items']) && is_array($pub_data['navigation']['items']))
            foreach ($pub_data['navigation']['items'] as $item) {
                if (
                        (!isset($item['front']) || !$item['front'])
                        && !in_array($item, $this->data['navigation']['items'])
                        && (isset($item['custom']) || (isset($item['auto']) && isset($urls[$item['auto']]) && !in_array($item['auto'], $used_autos)))
                    ) {
                    $this->data['navigation']['items'][] = $item;
                }
            }
        }

        $this->data = json_encode($this->data);
    }
}

/* End of file category.php */
/* Location: ./application/models/draft.php */
