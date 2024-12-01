<?php

class Koken extends DataMapper
{
    public function _get_site_order($type)
    {
        $url = new Url();
        $current = $url->order_by('id DESC')->limit(1)->get();

        $config = unserialize($current->data);

        if (in_array($type, ['content', 'essay'])) {
            $sort = ['published_on', 'desc'];
        } else {
            $sort = ['manual', 'asc'];
        }

        foreach ($config as $url_conf) {
            if ($url_conf['type'] === $type && isset($url_conf['data']['order'])) {
                $sort = explode(' ', (string) $url_conf['data']['order']);
                break;
            }
        }

        if ($type === 'album' && $sort[0] === 'captured_on') {
            $sort[0] = 'published_on';
        }

        if (in_array($type, ['content', 'essay']) && $sort[0] === 'manual') {
            $sort = ['published_on', 'desc'];
        }

        return ['by' => $sort[0], 'direction' => $sort[1]];
    }

    public function _do_tag_filtering($options)
    {
        if ($options['tags_not']) {
            $not = true;
            $options['tags'] = $options['tags_not'];
        } else {
            $not = false;
        }

        $tags = explode(',', urldecode((string) $options['tags']));

        if ($options['match_all_tags'] || $not) {
            $content_ids = false;
            $model = $this->model === 'album' ? 'albums' : $this->model;

            foreach ($tags as $tag) {
                $t = new Tag();
                $t->where('name', $tag)->get();

                if ($t->exists()) {
                    $tag_content_ids = [];
                    foreach ($t->{$model}->select('id')->get_iterated() as $content) {
                        $tag_content_ids[] = $content->id;
                    }

                    if ($content_ids === false) {
                        $content_ids = $tag_content_ids;
                    } else {
                        if ($options['match_all_tags']) {
                            $content_ids = array_intersect($content_ids, $tag_content_ids);
                        } else {
                            $content_ids = array_merge($content_ids, $tag_content_ids);
                        }
                    }
                }
            }

            if ($not) {
                $this->where_not_in('id', $content_ids);
            } else {
                $this->where_in('id', $content_ids);
            }
        } else {
            $this->distinct();
            $this->group_start();

            foreach ($tags as $tag) {
                $t = new Tag();
                $t->where('name', $tag)->get();

                if ($t->exists()) {
                    $this->or_where_related('tag', 'id', $t->id);
                }
            }
            $this->group_end();
        }
    }

    public function _update_tag_counts()
    {
        foreach ($this->tags->get_iterated() as $tag) {
            $tag->update_counts($this->model);
        }

        foreach ($this->categories->get_iterated() as $category) {
            $category->update_counts($this->model);
        }
    }

    public function update_counts($which = 'all')
    {
        if ($which === 'all' || $which === 'album') {
            $this->album_count = $this->album->where('visibility', 0)->where('total_count >', 0)->where('deleted', 0)->count();
        }

        if ($which === 'all' || $which === 'text') {
            $this->text_count = $this->text->where('published', 1)->count();
        }

        if ($which === 'all' || $which === 'content') {
            $this->content_count = $this->content->where('visibility', 0)->where('deleted', 0)->count();
        }

        $this->save();
    }

    public function _format_tags($tags)
    {
        $t = new Tag();

        $model = $this->model;

        $existing = [];
        foreach ($this->tags->select('id,name')->get_iterated() as $tag) {
            $existing[] = $tag->name;
        }

        if (empty($tags)) {
            $remove = $existing;
        } else {
            $tags = koken_format_tags($tags);

            $add = array_diff($tags, $existing);
            $remove = array_diff($existing, $tags);

            foreach ($add as $tag) {
                $t->get_by_name($tag);

                if (!$t->exists()) {
                    $t->name = $tag;
                }

                $t->last_used = time();
                $t->save($this);
                $t->update_counts($this->model);
            }
        }

        foreach ($remove as $tag) {
            $t->get_by_name($tag);

            if ($t->exists()) {
                $this->delete($t);
                $t->update_counts($this->model);
            }
        }
    }

    public function _eager_load_tags($data)
    {
        $ids = [];

        foreach ($data as $content) {
            $ids[] = $content->id;
        }

        $db_config = Shutter::get_db_configuration();

        switch ($this->model) {
            case 'text':
                $join_table = $db_config['prefix'] . 'join_tags_text';
                break;

            case 'content':
                $join_table = $db_config['prefix'] . 'join_content_tags';
                break;

            case 'album':
                $join_table = $db_config['prefix'] . 'join_albums_tags';
                break;
        }

        $join_field = $this->model . '_id';

        $tag = new Tag();
        $tag->select($db_config['prefix'] . 'tags.*, ' . $join_table . '.' . $this->model . '_id as ' . $join_field)
            ->where_related($this->model, 'id', $ids)
            ->order_by('name ASC')
            ->get_iterated();

        $tag_map = [];
        $tag_cache = [];
        foreach ($tag as $t) {
            if (isset($tag_cache[$t->name])) {
                $tag_as_array = $tag_cache[$t->name];
            } else {
                $tag_as_array = $t->_tag_for_output($this->model);
                $tag_cache[$t->name] = $tag_as_array;
            }

            $key = 'c' . $t->{$join_field};
            if (isset($tag_map[$key])) {
                $tag_map[$key][] = $tag_as_array;
            } else {
                $tag_map[$key] = [$tag_as_array];
            }
        }

        return $tag_map;
    }

    public function _tag_for_output($model)
    {
        $t = $this->to_array();
        [$t['__koken_url'], $t['url']] = $this->url(['limit_to' => $model === 'text' ? 'essays' : $model]);
        return $t;
    }

    public function _get_tags_for_output($options = [])
    {
        if (isset($options['eager_tags'])) {
            return $options['eager_tags'];
        } else {
            $db_config = Shutter::get_db_configuration();

            $tags = $this->tags->order_by($db_config['prefix'] . 'tags.name ASC')->get_iterated();
            $arr = [];

            foreach ($tags as $tag) {
                $arr[] = $tag->_tag_for_output($this->model);
            }

            return $arr;
        }
    }

    public function edit_slug()
    {
        if (isset($this->current_slug)) {
            if ($this->current_slug === $this->slug) {
                return;
            }

            $base = strtolower(static::class);

            if ($base === 'text') {
                $base = $this->page_type < 1 ? 'essay' : 'page';
            }

            if (preg_match('/[^a-z0-9\-]/', $this->slug)) {
                throw new Exception('URL slugs can only contain letters, numbers and hyphens.');
            }

            $s = new Slug();

            if ($s->where('id', "$base.{$this->slug}")->count() > 0) {
                throw new Exception('This URL slug is already in use. Please enter a different value.');
            } else {
                $old_slugs = explode(',', trim($this->old_slug, ','));
                array_unshift($old_slugs, $this->current_slug);
                $this->old_slug = ',' . implode(',', $old_slugs) . ',';
                $this->db->query("DELETE FROM {$s->table} WHERE id = '$base.{$this->current_slug}'");
                $this->db->query("INSERT INTO {$s->table}(id) VALUES ('$base.{$this->slug}')");
                return true;
            }
        }

        return false;
    }

    public function _validate_time($field)
    {
        $val = $this->{$field};
        $previous = 'old_' . $field;

        if (is_numeric($val)) {
            return strlen($val) <= 11;
        } elseif ($val === 'captured_on' && $field === 'published_on') {
            $s = new Setting();
            $s->where('name', 'site_timezone')->get();
            $tz = new DateTimeZone($s->value ?? 'UTC');
            $offset = $tz->getOffset(new DateTime(date('c', $this->captured_on), new DateTimeZone('UTC')));
            $this->published_on = $this->captured_on - $offset;
            return true;
        } elseif (isset($this->{$previous})) {
            $test = trim(preg_replace('/\-?\s*(year|month|day|hour|second)s?/', '', $test));
            if (strlen($test) === 0) {
                $diff = time() - strtotime((string) $val);
                $this->{$field} = $this->{$previous} - $diff;
                return true;
            }
        }
        return false;
    }
}
