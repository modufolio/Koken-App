<?php

class Text extends Koken
{
    public string $table = 'text';

    public array $has_one = ['featured_image' => ['class' => 'content']];

    public array $has_many = ['category', 'album', 'tag'];

    public array $validation = ['internal_id' => ['label' => 'Internal id', 'rules' => ['internalize', 'required']], 'page_type' => ['rules' => ['validate_type']], 'slug' => ['rules' => ['slug', 'required']], 'created_on' => ['rules' => ['validate_created_on']], 'title' => ['rules' => ['format_title'], 'get_rules' => ['readify']], 'draft_title' => ['rules' => ['format_title']], 'draft' => ['rules' => ['format_content']], 'content' => ['rules' => ['format_content']], 'published' => ['rules' => ['re_slug']]];

    private $ids_for_array_index = [];

    private function _array_index_callback($matches)
    {
        $index = array_search($matches[1], $this->ids_for_array_index);
        return '<koken:array_access index="' . $index . '">' . $matches[2] . '</koken:array_access>';
    }

    public function _format_content($field)
    {
        $this->{$field} = rawurldecode(trim((string) $this->{$field}));
        if ($field === 'content') {
            $this->draft = $this->content;
        }
        return true;
    }

    public function _format_title($field)
    {
        $this->{$field} = rawurldecode(trim((string) $this->{$field}));
        if ($field === 'title') {
            $this->draft_title = $this->title;
        }
        return true;
    }

    public function _validate_type()
    {
        $values = ['essay', 'page'];
        if (in_array($this->page_type, $values)) {
            $this->page_type = array_search($this->page_type, $values);
        } else {
            return false;
        }
    }

    public function _validate_created_on()
    {
        $val = $this->created_on;
        if (is_numeric($val) && strlen($val) <= 10) {
            return true;
        }
        return false;
    }

    /**
     * Create internal ID if one is not present
     */
    public function _internalize($field)
    {
        $this->{$field} = koken_rand();
    }

    public function _re_slug($field)
    {
        if ($this->published > 0) {
            $this->_slug('slug');
        }
    }

    public function _slug($field)
    {
        if ($this->edit_slug()) {
            return true;
        }

        $this->load->helper(['url', 'text', 'string']);
        $slug = reduce_multiples(
            strtolower(
                        (string) url_title(
                            convert_accented_characters($this->title),
                            'dash'
                        )
                    ),
            '-',
            true
        );

        if (empty($slug)) {
            $t = new Text();
            $max = $t->select_max('id')->get();
            $slug = $max->id + 1;
        }

        if (is_numeric($slug)) {
            $slug = "$slug-1";
        }

        if ($this->slug === $slug || (!empty($this->slug) && $this->slug !== '__generate__')) {
            return;
        }

        $s = new Slug();

        // Need to lock the table here to ensure that requests arriving at the same time
        // still get unique slugs
        if ($this->has_db_permission('lock tables')) {
            $this->db->query("LOCK TABLE {$s->table} WRITE");
            $locked = true;
        } else {
            $locked = false;
        }

        $page_type = is_numeric($this->page_type) ? $this->page_type : 0;
        $prefix = $page_type === 1 ? 'page' : 'essay';

        while ($s->where('id', "$prefix.$slug")->count() > 0) {
            $slug = increment_string($slug, '-');
        }

        $this->db->query("INSERT INTO {$s->table}(id) VALUES ('$prefix.$slug')");

        if ($locked) {
            $this->db->query('UNLOCK TABLES');
        }

        $this->slug = $slug;
    }

    /**
     * Constructor: calls parent constructor
     */
    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    public function listing($params)
    {
        $sort = $this->_get_site_order('essay');

        $options = ['page' => 1, 'order_by' => $sort['by'], 'order_direction' => $sort['direction'], 'tags' => false, 'tags_not' => false, 'match_all_tags' => false, 'limit' => 100, 'published' => 1, 'category' => false, 'category_not' => false, 'featured' => null, 'type' => false, 'state' => false, 'year' => false, 'year_not' => false, 'letter' => false, 'month' => false, 'month_not' => false, 'day' => false, 'day_not' => false, 'render' => true];

        $options = array_merge($options, $params);

        if (isset($params['order_by']) && !isset($params['order_direction'])) {
            $options['order_direction'] = in_array($params['order_by'], ['title']) ? 'ASC' : 'DESC';
        }

        if (!$options['auth']) {
            $options['state'] = 'published';
        }

        if ($options['featured'] == 1 && !isset($params['order_by'])) {
            $options['order_by'] = 'featured_on';
        }

        if (is_numeric($options['limit']) && $options['limit'] > 0) {
            $options['limit'] = min($options['limit'], 100);
        } else {
            $options['limit'] = 100;
        }
        if ($options['type']) {
            if ($options['type'] === 'essay') {
                $this->where('page_type', 0);
            } elseif ($options['type'] === 'page') {
                $this->where('page_type', 1);
            }
        }

        if ($options['auth'] && $options['type'] === 'page') {
            $options['state'] = false;
            $options['order_by'] = 'modified_on';
        }

        if ($options['state']) {
            if ($options['state'] === 'published') {
                $this->where('published', 1);
            } elseif ($options['state'] === 'draft' && $options['order_by'] !== 'published_on') {
                $this->where('published', 0);
            }
        }

        if ($options['order_by'] === 'published_on') {
            $this->where('published', 1);
        }

        if ($options['tags'] || $options['tags_not']) {
            $this->_do_tag_filtering($options);
        }

        if (!is_null($options['featured'])) {
            $this->where('featured', $options['featured']);
            if ($options['featured']) {
                $this->where('published', 1);
            }
        }
        if ($options['category']) {
            $this->where_related('category', 'id', $options['category']);
        } elseif ($options['category_not']) {
            $cat = new Text();
            $cat->select('id')->where_related('category', 'id', $options['category_not'])->get_iterated();
            $cids = [];
            foreach ($cat as $c) {
                $cids[] = $c->id;
            }
            $this->where_not_in('id', $cids);
        }

        if ($options['order_by'] === 'created_on' || $options['order_by'] === 'published_on' || $options['order_by'] === 'modified_on') {
            $bounds_order = $options['order_by'];
        } else {
            $bounds_order = 'published_on';
        }

        $s = new Setting();
        $s->where('name', 'site_timezone')->get();
        $tz = new DateTimeZone($s->value ?? 'UTC');
        $offset = $tz->getOffset(new DateTime('now', new DateTimeZone('UTC')));

        if ($offset === 0) {
            $shift = '';
        } else {
            $shift = ($offset < 0 ? '-' : '+') . abs($offset);
        }

        // Do this before date filters are applied, and only if sorted by created_on
        $bounds = $this->get_clone()
                    ->select('COUNT(DISTINCT ' . $this->table . '.id) as count, MONTH(FROM_UNIXTIME(' . $bounds_order . $shift . ')) as month, YEAR(FROM_UNIXTIME(' . $bounds_order . $shift . ')) as year')
                    ->group_by('month,year')
                    ->order_by('year')
                    ->get_iterated();

        $dates = [];
        foreach ($bounds as $b) {
            if (!isset($dates[$b->year])) {
                $dates[$b->year] = [];
            }

            $dates[$b->year][$b->month] = (int) $b->count;
        }

        if (in_array($options['order_by'], ['created_on', 'published_on', 'modified_on'])) {
            $date_col = $options['order_by'];
        } else {
            $date_col = 'published_on';
        }

        // So featured_image eager loading doesn't break this down (ambiguous column name)
        $date_col = $this->table . '.' . $date_col;

        if ($options['year'] || $options['year_not']) {
            if ($options['year_not']) {
                $options['year'] = $options['year_not'];
                $compare = ' !=';
            } else {
                $compare = '';
            }
            $this->where('YEAR(FROM_UNIXTIME(' . $date_col . $shift . '))' . $compare, $options['year']);
        }
        if ($options['month'] || $options['month_not']) {
            if ($options['month_not']) {
                $options['month'] = $options['month_not'];
                $compare = ' !=';
            } else {
                $compare = '';
            }
            $this->where('MONTH(FROM_UNIXTIME(' . $date_col . $shift . '))' . $compare, $options['month']);
        }
        if ($options['day'] || $options['day_not']) {
            if ($options['day_not']) {
                $options['day'] = $options['day_not'];
                $compare = ' !=';
            } else {
                $compare = '';
            }
            $this->where('DAY(FROM_UNIXTIME(' . $date_col . $shift . '))' . $compare, $options['day']);
        }

        if ($options['letter']) {
            if ($options['letter'] === 'num') {
                $this->where('title <', 'a');
            } else {
                $this->like('title', $options['letter'], 'after');
            }
        }

        $final = $this->paginate($options);

        $final['dates'] = $dates;

        $this->include_related('featured_image', null, true, true);
        $this->include_related_count('albums', null, ['visibility' => 0]);
        $this->include_related_count('categories');

        $data = $this->order_by($options['order_by'] .' ' . $options['order_direction'] . ', id ' . $options['order_direction'])->get_iterated();

        if (!$options['limit']) {
            $final['per_page'] = $data->result_count();
            $final['total'] = $data->result_count();
        }

        $final['counts'] = ['total' => $final['total']];

        $final['text'] = [];

        $final['sort'] = $sort;

        $tag_map = $this->_eager_load_tags($data);

        foreach ($data as $page) {
            $tags = $tag_map['c' . $page->id] ?? [];
            $options['eager_tags'] = $tags;
            $final['text'][] = $page->to_array($options);
        }
        return $final;
    }

    public function to_array($options = [])
    {
        $options = array_merge(['auth' => false, 'render' => true, 'expand' => false], $options);

        $koken_url_info = $this->config->item('koken_url_info');

        $exclude = ['deleted', 'total_count', 'video_count', 'audio_count', 'featured_image_id', 'custom_featured_image', 'tags_old', 'old_slug'];
        $dates = ['created_on', 'modified_on', 'published_on', 'featured_on'];
        $strings = ['title', 'content', 'excerpt'];
        $bools = ['published', 'featured'];

        if (!$this->published) {
            $this->published_on = time();
        }
        [$data, $public_fields] = $this->prepare_for_output($options, $exclude, $bools, $dates, $strings);

        if (empty($data['draft'])) {
            $data['draft'] = $data['content'];
        }

        if (empty($data['draft_title'])) {
            $data['draft_title'] = $data['title'];
        }

        if (!$data['featured']) {
            unset($data['featured_on']);
        }

        if ($data['page_type'] != 0) {
            unset($data['featured']);
            unset($data['featured_on']);
        }

        if (!$options['auth']) {
            unset($data['internal_id']);
            unset($data['draft']);
            unset($data['draft_title']);
        }

        if (array_key_exists('page_type', $data)) {
            $data['page_type'] = match ($data['page_type']) {
                1 => 'page',
                default => 'essay',
            };
        }

        $data['__koken__'] = $data['page_type'];

        $data['tags'] = $this->_get_tags_for_output($options);

        $data['categories'] = ['count' => is_null($this->category_count) ? $this->categories->count() : (int) $this->category_count, 'url' => $koken_url_info->base . 'api.php?/text/' . $data['id'] . '/categories'];

        $data['topics'] = ['count' => is_null($this->album_count) ? $this->albums->count() : (int) $this->album_count, 'url' => $koken_url_info->base . 'api.php?/text/' . $data['id'] . '/topics'];

        if (is_numeric($this->featured_image_id) && !$this->featured_image->id) {
            $this->featured_image->get();
        }

        if ($this->featured_image->id && $this->featured_image->deleted == 0) {
            $data['featured_image'] = $this->featured_image->to_array();
        } elseif (!empty($this->custom_featured_image)) {
            $c = new Content();
            $data['featured_image'] = $c->to_array_custom($this->custom_featured_image);
        } else {
            $data['featured_image'] = false;
        }

        $rendered = Shutter::shortcodes($data['content'], [$this, $options]);

        if ($options['render']) {
            if ($options['expand']) {
                $rendered = preg_replace('/\[read_more([^\]]+)?\]/', '<a id="more"></a>', (string) $rendered);
            } else {
                $more = strpos((string) $rendered, '[read_more');

                if ($more !== false) {
                    preg_match('/\[read_more(?:\s*label="(.*)")?\]/', (string) $rendered, $matches);
                    $rendered = substr((string) $rendered, 0, $more);
                    $data['read_more'] = true;
                    $data['read_more_label'] = count($matches) > 1 ? $matches[1] : 'Read more';
                }
            }
        }

        if (!isset($data['read_more'])) {
            $data['read_more'] = false;
        }

        preg_match_all('/<koken:load source="content" filter:id="(\d+)">/', (string) $rendered, $loads);

        if (count($loads[0]) > 1) {
            $this->ids_for_array_index = array_unique($loads[1]);
            $rendered = '<koken:load source="contents" filter:id="' . join(',', $this->ids_for_array_index) . '">' . $rendered . '</koken:load>';
            $rendered = preg_replace_callback('/<koken:load source="content" filter:id="(\d+)">(.*)<\/koken:load>/msU', [$this, '_array_index_callback'], $rendered);
        }

        if (empty($options) || (isset($options['render']) && $options['render'])) {
            $data['content'] = $rendered;
            if (isset($data['draft'])) {
                $data['draft'] = Shutter::shortcodes($data['draft'], [$this, $options]);
            }
        }

        if (empty($data['excerpt'])) {
            $rendered = preg_replace('/<script.*>.*?<\/script>/msU', '', (string) $rendered);
            $rendered = preg_replace('/<figure class="k-content-embed">.*?<\/figure>/msU', '', (string) $rendered);
            $clean_parts = explode(' ', (string) preg_replace('/([\.\?\!]+)([^\s]\s*[a-z][a-z\s]*)/', '$1 $2', trim(strip_tags((string) preg_replace('/\{\{.*\}\}/', '', html_entity_decode((string) $rendered))))));
            $excerpt = '';
            while (count($clean_parts) && ($next = array_shift($clean_parts)) && strlen(trim($excerpt) . ' ' . trim($next)) <= 254) {
                $excerpt .= ' ' . trim($next);
            }
            $data['excerpt'] = trim($excerpt);
            if (count($clean_parts)) {
                $data['excerpt'] = preg_replace('/[^\w]$/u', '', $data['excerpt']) . '…';
            }

            $more = strpos($data['excerpt'], '[read_more');
            if ($more !== false) {
                $data['excerpt'] = trim(substr($data['excerpt'], 0, $more));
            }
        }

        if (isset($options['order_by']) && in_array($options['order_by'], ['created_on', 'modified_on', 'published_on'])) {
            $data['date'] =& $data[ $options['order_by'] ];
        } elseif ($data['page_type'] === 'essay') {
            $data['date'] =& $data['published_on'];
        }

        $cat = $options['category'] ?? (isset($options['context']) && str_starts_with((string) $options['context'], 'category-') ? str_replace('category-', '', $options['context']) : false);

        if ($cat) {
            if (is_numeric($cat)) {
                foreach ($this->categories->get_iterated() as $c) {
                    if ($c->id == $cat) {
                        $cat = $c->slug;
                        break;
                    }
                }
            }
        }

        $data['url'] = $this->url(
            ['date' => $data['published_on'], 'tag' => $options['tags'] ?? (isset($options['context']) && str_starts_with((string) $options['context'], 'tag-') ? str_replace('tag-', '', $options['context']) : false), 'category' => $cat]
        );

        if ($data['url']) {
            [$data['__koken_url'], $data['url']] = $data['url'];
            $data['canonical_url'] = $data['url'];
        }

        return Shutter::filter('api.text', [$data, $this, $options]);
    }
}

/* End of file page.php */
/* Location: ./application/models/page.php */
