<?php

class Category extends Koken
{
    public array $has_many = ['content', 'album', 'text'];

    public array $validation = ['title' => ['label' => 'Title', 'rules' => ['required']], 'slug' => ['rules' => ['slug', 'required']]];

    /**
     * Constructor: calls parent constructor
     */
    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    public function get_grouped_status($ids, $model)
    {
        $mixed = $this->distinct()->select('id')->where_in_related(strtolower((string) $model), 'id', $ids)->get_iterated();

        $mixed_ids = [];
        foreach ($mixed as $cat) {
            $mixed_ids[] = $cat->id;
        }

        $model = new $model();
        $model->select('id')->where_in('id', $ids)->include_related('category')->get_iterated();

        $common_ids = $content_id = $cats = false;
        $aggregate = [];

        foreach ($model as $item) {
            if ($item->category_id === null) {
                $aggregate = [];
                break;
            }

            if (!isset($aggregate[$item->id])) {
                $aggregate[$item->id] = [(int) $item->category_id];
            } else {
                $aggregate[$item->id][] = (int) $item->category_id;
            }
        }

        $common_ids = array_shift($aggregate);

        foreach ($aggregate as $category_list) {
            $common_ids = array_intersect($common_ids, $category_list);
        }

        if (!is_array($common_ids)) {
            $common_ids = [];
        }

        return ['mixed' => array_values(array_diff($mixed_ids, $common_ids)), 'common' => $common_ids];
    }

    public function listing($params = [])
    {
        $options = ['auth' => false, 'page' => 1, 'limit' => 20, 'order_by' => 'title', 'order_direction' => 'asc', 'category' => false, 'limit_to' => false, 'include_empty' => true];

        $options = array_merge($options, $params);

        $this->select_func('', ['@content_count', '+', '@text_count', '+', '@album_count'], 'total_count');
        $this->select('*');

        if (!is_numeric($options['limit'])) {
            $options['limit'] = false;
        }

        if (is_numeric($options['category'])) {
            $this->where('id', $options['category']);
        }

        if ($options['order_by'] !== 'count' && str_contains((string) $options['order_by'], 'count')) {
            if ($options['order_by'] === 'essay_count') {
                $options['order_by'] = 'text_count';
            }
            $this->where("{$options['order_by']} >", 0);
        } elseif ($options['limit_to']) {
            $limit = str_replace('essay', 'text', rtrim((string) $options['limit_to'], 's')) . '_count';

            if (!$options['auth']) {
                $this->where("$limit >", 0);
            }
        } elseif (!$options['include_empty']) {
            $this->where_func('', ['@content_count', '+', '@text_count', '+', '@album_count', '>', 0], null);
        }

        $final = $this->paginate($options);

        if ($options['order_by'] === 'count') {
            $options['order_by'] = 'total_count';
        }

        if (str_contains((string) $options['order_by'], 'count') && !isset($params['order_direction'])) {
            $options['order_direction'] = 'DESC';
        }

        $this->order_by("{$options['order_by']} {$options['order_direction']}");

        $data = $this->get_iterated();
        if (!$options['limit']) {
            $final['per_page'] = $data->result_count();
            $final['total'] = $data->result_count();
        }

        $koken_url_info = $this->config->item('koken_url_info');
        $base = $koken_url_info->base;

        $final['categories'] = [];
        foreach ($data as $category) {
            $cat = $category->to_array($options);
            $cat['items'] = $base . 'api.php?/categories/' . $category->id;
            $final['categories'][] = $cat;
        }

        return $final;
    }

    public function _slug($field)
    {
        if (!empty($this->slug) && $this->slug !== '__generate__') {
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
            $t = new Category();
            $max = $t->select_max('id')->get();
            $slug = $max->id + 1;
        }

        if (is_numeric($slug)) {
            $slug = "$slug-1";
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

        while ($s->where('id', "category.$slug")->count() > 0) {
            $slug = increment_string($slug, '-');
        }

        $this->db->query("INSERT INTO {$s->table}(id) VALUES ('category.$slug')");

        if ($locked) {
            $this->db->query('UNLOCK TABLES');
        }

        $this->slug = $slug;
    }

    public function to_array($options = [])
    {
        $exclude = ['content_count', 'album_count', 'text_count', 'count'];
        [$data, $public_fields] = $this->prepare_for_output($options, $exclude);
        if (isset($options['limit_to']) && $options['limit_to']) {
            $key = rtrim((string) $options['limit_to'], 's') . '_count';
            $key = $key === 'essay_count' ? 'text_count' : $key;
            $data['counts'] = [$options['limit_to'] => (int) $this->{$key}, 'total' => (int) $this->{$key}];
        } else {
            $data['counts'] = ['content' => (int) $this->content_count, 'albums' => (int) $this->album_count, 'essays' => (int) $this->text_count, 'total' => $this->content_count + $this->album_count + $this->text_count];
        }

        $data['__koken__'] = 'category';

        $data['url'] = $this->url($options);

        if ($data['url']) {
            [$data['__koken_url'], $data['url']] = $data['url'];
        }

        return $data;
    }
}

/* End of file category.php */
/* Location: ./application/models/category.php */
