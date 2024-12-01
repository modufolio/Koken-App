<?php

class Tag extends Koken
{
    public array $has_many = ['content', 'album', 'text'];

    public function to_array($params = [])
    {
        $params = array_merge(['limit_to' => false], $params);

        $data = ['id' => $this->id, 'title' => $this->name, '__koken__' => 'tag'];

        $data['counts'] = ['content' => (int) $this->content_count, 'albums' => (int) $this->album_count, 'essays' => (int) $this->text_count];

        $data['counts']['total'] = $data['counts']['content'] + $data['counts']['albums'] + $data['counts']['essays'];

        $data['url'] = $this->url($params);

        if ($data['url']) {
            [$data['__koken_url'], $data['url']] = $data['url'];
        }

        $koken_url_info = $this->config->item('koken_url_info');
        $base = $koken_url_info->base;

        $data['items'] = $base . 'api.php?/tags/' . $this->id;

        return $data;
    }

    public function listing($params = [])
    {
        $defaults = ['order_by' => 'count', 'order_direction' => 'DESC', 'floor' => 1, 'tags' => false, 'limit_to' => false, 'page' => false, 'limit' => false];

        $options = array_merge($defaults, $params);

        if ($options['order_by'] === 'essay_count') {
            $options['order_by'] = 'text_count';
        } elseif ($options['order_by'] === 'title') {
            $options['order_by'] = 'name';
            if (!isset($params['order_direction'])) {
                $options['order_direction'] = 'ASC';
            }
        } elseif (!str_contains((string) $options['order_by'], 'count')) {
            $options['order_by'] = 'count';
        }

        if ($options['limit_to']) {
            $count_col = str_replace('essay', 'text', rtrim((string) $options['limit_to'], 's')) . '_count';
            if (!in_array($options['order_by'], ['last_used', 'name'])) {
                $options['order_by'] = $count_col;
            }

            $this->where($count_col . ' >=', $options['floor']);
        } else {
            $this->where_func('', ['@content_count', '+', '@text_count', '+', '@album_count', '>=', $options['floor']], null);
        }

        $final = $this->paginate($options);

        if ($options['order_by'] === 'count') {
            $this->order_by_func('', ['@content_count', '+', '@text_count', '+', '@album_count'], $options['order_direction']);
        } else {
            $this->order_by($options['order_by'] . ' ' . $options['order_direction']);
        }

        // If count based order, add secondary sort to break ties
        if (str_contains((string) $options['order_by'], 'count')) {
            $this->order_by('name ASC');
        }

        $data = $this->get_iterated();

        if (!$options['limit']) {
            $final['per_page'] = $data->result_count();
            $final['total'] = $data->result_count();
        }

        $final['tags'] = [];
        foreach ($data as $tag) {
            $final['tags'][] = $tag->to_array($options);
        }
        return $final;
    }
}

/* End of file trash.php */
/* Location: ./application/models/trash.php */
