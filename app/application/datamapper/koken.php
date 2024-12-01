<?php

class DMZ_Koken
{
    private $urls = false;
    private $url_data = false;
    private $segments = false;
    private $base = false;
    private $tz = false;

    public function get_data()
    {
        if (!$this->urls) {
            $this->form_urls();
        }
        return $this->url_data;
    }
    public function get_base()
    {
        if (!$this->base) {
            $s = new Setting();
            $s->where('name', 'site_url')->get();
            if ($s->value === 'default') {
                $CI =& get_instance();
                $koken_url_info = $CI->config->item('koken_url_info');
                $this->base = $koken_url_info->base;
            } else {
                $this->base = 'http://' . $_SERVER['HTTP_HOST'] . $s->value;
            }
        }
        return rtrim((string) $this->base, '/') . (defined('DRAFT_CONTEXT') ? '/preview.php?' : (KOKEN_REWRITE ? '' : '/index.php?'));
    }

    private function get_tz()
    {
        if (!$this->tz) {
            $s = new Setting();
            $s->where('name', 'site_timezone')->get();
            $this->tz = $s->value ?? 'UTC';
        }
        return $this->tz;
    }

    public function form_urls()
    {
        if ($this->urls) {
            return $this->urls;
        }

        $d = new Draft();
        $context = defined('DRAFT_CONTEXT') ? DRAFT_CONTEXT : false;
        $path = '';

        if (!$context) {
            $d->where('current', 1)->get();
            $path = $d->path;
        } elseif (is_numeric(DRAFT_CONTEXT)) {
            $d->get_by_id(DRAFT_CONTEXT);
            $path = $d->path;
        } else {
            $path = DRAFT_CONTEXT;
        }

        [$this->urls, $this->url_data, , $this->segments] = $d->setup_urls(FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR);
        return $this->urls;
    }

    private function get_url($model)
    {
        if (!$this->urls) {
            $this->form_urls();
        }

        return $this->urls[$model] ?? false;
    }

    public function url($object, $options = [])
    {
        $model = $object->model;
        $tail = '';
        $content = false;

        if ($model === 'tag') {
            $object->slug = $object->name;
            if (is_numeric($object->slug)) {
                $object->slug = 'tag-' . $object->slug;
            }
        }

        if ($model === 'text') {
            $model = $object->page_type > 0 ? 'page' : 'essay';
        }
        if ($model === 'album' && $object->album_type == 2) {
            $model = 'set';
        }
        if ($model === 'content' && isset($options['album']) && $options['album']) {
            if ($options['album']->album_type == 2) {
                $actual_album = new Album();
                $actual_album
                    ->where_related('content', 'id', $object->id)
                    ->where('left_id >=', $options['album']->left_id)
                    ->where('right_id <=', $options['album']->right_id)
                    ->get();
                $options['album'] = $actual_album;
            }

            $model = 'album';
            $content_template = $this->get_url('content');
            $content_url = $this->url_data['content']['url'];
            $tail = $this->segments['content'] . '/' . (!str_contains((string) $content_url, 'slug') ? ':content_id' : ':content_slug') . '/';
            if (!$content_template) {
                $tail .= 'lightbox/';
            }
            $content = $object;
            $object = $options['album'];
            $date = $options['album']->published_on;
        } elseif (isset($options['date'])) {
            $date = $options['date']['timestamp'];
        }

        if (isset($options['tag']) && $options['tag'] && !str_contains((string) $options['tag'], ',') && $model !== 'set' && $this->get_url("tag_$model")) {
            $content_template = $this->get_url($model);
            $content_url = $this->url_data[$model]['url'];
            if (is_numeric($options['tag'])) {
                $options['tag'] = 'tag-' . $options['tag'];
            }
            $template = str_replace(':tag_slug', str_replace(' ', '+', $options['tag']), $this->urls["tag_$model"]);
        } elseif (isset($options['category']) && $options['category'] && $model !== 'set' && isset($this->urls["category_$model"])) {
            $content_template = $this->get_url($model);
            $content_url = $this->url_data[$model]['url'];
            $template = str_replace(':category_slug', $options['category'], $this->urls["category_$model"]);
        } elseif ($model === 'content' && isset($options['favorite']) && $options['favorite']) {
            $template = $this->get_url('favorite');
        } elseif ($model === 'content' && isset($options['feature']) && $options['feature']) {
            $template = $this->get_url('feature');
        } else {
            if (isset($options['limit_to']) && $options['limit_to']) {
                $model .= '_' . rtrim((string) $options['limit_to'], 's') . 's';
            }
            $template = $this->get_url($model);
        }

        if (!$template) {
            if ($model === 'content') {
                $template = '/' . $this->segments['content'] . '/:slug/lightbox/';
                $tail = '';
            } else {
                return false;
            }
        }

        $template .= $tail;

        $data = [];

        if ((isset($object->visibility) && (int) $object->visibility === 1) || (isset($object->listed) && $object->listed < 1)) {
            $data['id'] = $data['slug'] = $object->internal_id;
        } else {
            $data = ['id' => $object->id, 'slug' => $object->slug];

            if ($model === 'tag' && is_numeric($data['slug'])) {
                $data['slug'] = 'tag-' . $data['slug'];
            }
        }

        if (isset($options['date'])) {
            date_default_timezone_set($this->get_tz());
            $data['year'] = date('Y', $date);
            $data['month'] = date('m', $date);
            $data['day'] = date('d', $date);
            date_default_timezone_set('UTC');
        }

        if ($content) {
            if ((int) $content->visibility === 1) {
                $data['content_id'] = $data['content_slug'] = $content->internal_id;
            } else {
                $data['content_id'] = $content->id;
                $data['content_slug'] = $content->slug;
            }
        }

        preg_match_all('/:([a-z_]+)/', $template, $matches);

        foreach ($matches[1] as $magic) {
            $template = str_replace(':' . $magic, urlencode($data[$magic]), $template);
        }

        return [$template, $this->get_base() . $template . (defined('DRAFT_CONTEXT') && !is_numeric(DRAFT_CONTEXT) ? '&preview=' . DRAFT_CONTEXT : '')];
    }

    public function prepare_for_output($object, $options, $exclude = [], $booleans = [], $dates = [], $strings = [])
    {
        if (isset($options['fields'])) {
            $fields = explode(',', (string) $options['fields']);
        } else {
            $fields = $object->fields;
        }
        $fields = array_diff($fields, $exclude);
        $public_fields = array_intersect($object->fields, $fields);
        $data = [];

        foreach ($public_fields as $name) {
            $val = $object->{$name};
            if (preg_match('/_on$/', (string) $name)) {
                if (is_numeric($val)) {
                    $val = ['datetime' => date('Y/m/d G:i:s', $val), 'timestamp' => (int) $val, 'utc' => $name !== 'captured_on'];
                } else {
                    $val = ['datetime' => null, 'timestamp' => null];
                }
            } elseif (in_array($name, $booleans)) {
                $val = (bool) $val;
            } elseif (in_array($name, $strings)) {
                $val = (string) $val;
            } elseif (is_numeric($val)) {
                $val = (float) $val;
            }
            $data[$name] = $val;
        }
        return [$data, $fields];
    }

    public function paginate($object, $options): array
    {
        $final = [];
        if ($options['limit']) {
            $total = $object->get_clone()->count_distinct();
            if (isset($options['cap']) && $options['cap'] < $total) {
                $total = $options['cap'];
            }
            $final['page'] = (int) $options['page'];
            $final['pages'] = ceil($total/$options['limit']);
            $final['per_page'] = min((int) $options['limit'], $total);
            $final['total'] = $total;
            if ($options['page'] == 1) {
                $start = 0;
            } else {
                $start = ($options['limit']*($options['page']-1));
            }
            $object->limit($options['limit'], $start);
        } else {
            $final = ['page' => 1, 'pages' => 1];
        }
        return $final;
    }

    public function has_db_permission($object, $perm): bool
    {
        $r = $object->db->query('SHOW GRANTS');

        $has = false;

        foreach ($r->result() as $row) {
            $row = (array) $row;
            $keys = array_keys($row);
            $permissions_str = strtolower((string) $row[$keys[0]]);

            preg_match('/grant (.*) privileges/', $permissions_str, $matches);
            if ($matches) {
                $p = $matches[1];
                $has = $p === 'all' || strpos($p, (string) $perm) !== -1;
            }

            if ($has) {
                return true;
            }
        }

        return false;
    }
}

/* End of file pagination.php */
/* Location: ./application/datamapper/pagination.php */
