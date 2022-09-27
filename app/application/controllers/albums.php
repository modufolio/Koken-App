<?php

class TreeSorter
{
    private static $sort;

    public static function sort(&$albums, $sort)
    {
        self::$sort = $sort;
        usort($albums, array('TreeSorter', 'sorter'));
    }

    public static function sorter($a, $b)
    {
        list($field, $direction) = explode(' ', self::$sort);

        $one = -1;
        $two = 1;

        if ($field === 'manual') {
            $field = 'left_id';
        }
        if (strtolower($direction) === 'desc') {
            $one = 1;
            $two = -1;
        }

        return $a[$field] < $b[$field] ? $one : $two;
    }
}

class Albums extends Koken_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function tree()
    {
        list($passed_params, ) = $this->parse_params(func_get_args());

        $a = new Album();

        $sort = $a->_get_site_order('album');

        $params = array_merge(array(
                'visibility' => 'public',
                'include_empty' => true,
                'order_by' => $sort['by'],
                'order_direction' => $sort['direction'],
            ), $passed_params);

        if (!isset($passed_params['visibility']) || !$this->auth) {
            $params['visibility'] = 'public';
        }

        if ($params['visibility'] !== 'public') {
            $params['order_by'] = 'title';
        }

        if ($params['order_by'] === 'manual') {
            $params['order_by'] = 'left_id';
        }

        if ($params['order_by'] === 'filename') {
            $params['order_by'] = 'title';
        }

        $visibility_values = array('public', 'unlisted', 'private');
        $visibility = array_search($params['visibility'], $visibility_values);

        if ($visibility === false) {
            $visibility = 0;
        }

        $a->select('id,title,album_type,sort,level,left_id,right_id,featured,total_count,published_on,modified_on,created_on')
                    ->where('visibility', $visibility)
                    ->where('deleted', 0)
                    ->order_by($params['order_by'] . ' ' . $params['order_direction']);

        if (!$params['include_empty']) {
            $a->where('total_count >', 0);
        }

        $a->get_iterated();

        $data = $levels = [];

        foreach ($a as $album) {
            if (!isset($levels['_' . $album->level])) {
                $levels['_' . $album->level] = [];
            }

            switch ($album->album_type) {
                case 2:
                    $type = 'set';
                    break;
                case 1:
                    $type = 'smart';
                    break;
                default:
                    $type = 'standard';
            }

            $arr = array(
                'id' => $album->id,
                'title' => $album->title,
                'album_type' => $type,
                'left_id' => (int) $album->left_id,
                'level' => (int) $album->level,
                'count' => (int) $album->total_count,
                'featured' => $album->featured == 1,
                'sort' => $album->sort,
                'published_on' => (int) $album->published_on,
                'created_on' => (int) $album->created_on,
                'modified_on' => (int) $album->modified_on,
                'visibility' => $visibility_values[$visibility],
            );

            $levels['_' . $album->level][$album->left_id] = $arr;
        }

        if (!empty($levels)) {
            $count = count($levels);
            $cycles = 0;
            for ($i = 0; $i < $count - 1; $i++) {
                $l = $count - $i;
                $next = '_' . ($l - 1);

                foreach ($levels["_$l"] as $left => $arr) {
                    while ($left--) {
                        if (isset($levels[$next][$left])) {
                            if (!isset($levels[$next][$left]['children'])) {
                                $levels[$next][$left]['children'] = [];
                            }
                            $levels[$next][$left]['children'][] = $arr;
                            break;
                        }
                    }
                }
            }

            ksort($levels);

            $array = array_keys($levels);
            $data = array_values($levels[array_shift($array)]);
        }

        function sort_children(&$album)
        {
            if (isset($album['children']) && $album['count']) {
                foreach ($album['children'] as &$child) {
                    sort_children($child);
                }

                TreeSorter::sort($album['children'], $album['sort']);
            }
        }

        foreach ($data as &$album) {
            sort_children($album);
        }

        $this->set_response_data($data);
    }

    public function categories()
    {
       [$params, $id] = $this->parse_params(func_get_args());
        $c = new Category();

        $params['auth'] = $this->auth;
        $params['limit_to'] = 'albums';

        if (strpos($id, ',') === false) {
            $final = $c->where_related('album', 'id', $id)->listing($params);
        } else {
            $final = $c->get_grouped_status(explode(',', $id), 'Album');
        }

        $this->set_response_data($final);
    }

    public function topics()
    {
       [$params, $id] = $this->parse_params(func_get_args());
        $t = new Text();

        $params['auth'] = $this->auth;

        $final = $t->where_related('album', 'id', $id)->listing($params);
        $this->set_response_data($final);
    }

    public function _order($order, $album = false)
    {
        $ids = explode(',', $order);
        $new_order_map = [];

        foreach ($ids as $key => $val) {
            $pos = $key + 1;
            $new_order_map[$val] = $pos;
        }

        $contents = new Album();
        $contents->where_in('id', $ids);

        $sql = $contents->get_sql() . ' ORDER BY FIELD(id, ' . join(',', $ids) . ')';
        $contents->query($sql);

        $next_slot = $album ? $album->left_id + 1 : 1;

        $this->db->trans_begin();
        $start = strtotime(gmdate("M d Y H:i:s", time()));

        foreach ($contents as $sub_album) {
            $size = ($sub_album->right_id - $sub_album->left_id) + 1;

            if ($sub_album->left_id != $next_slot) {
                $delta = $sub_album->left_id - $next_slot;
                $delta = $delta >= 0 ? '- ' . $delta : '+ '. abs($delta);
                $_a = new Album();
                $_a->where('left_id >=', $sub_album->left_id)
                        ->where('right_id <=', $sub_album->right_id)
                        ->where('level >=', $sub_album->level)
                        ->where('modified_on <', $start)
                        ->update(array(
                            'left_id' => "left_id $delta",
                            'right_id' => "right_id $delta",
                            'modified_on' => $start
                        ), false);
            }
            $next_slot += $size;
        }
        $this->db->trans_complete();
    }

    public function index()
    {
        list($params, $id, $slug) = $this->parse_params(func_get_args());
        $params['auth'] = $this->auth;
        // Create or update
        if ($this->method != 'get') {
            $a = new Album();
            switch ($this->method) {
                case 'post':
                case 'put':
                    if ($this->method == 'put') {
                        if (isset($params['order'])) {
                            $this->_order($params['order']);
                            $this->redirect("/albums");
                        } elseif (is_null($id)) {
                            $this->error('403', 'Required parameter "id" not present.');
                            return;
                        }
                        // Update
                        $a->get_by_id($id);
                        if (!$a->exists()) {
                            $this->error('404', "Album with ID: $id not found.");
                            return;
                        }

                        $a->old_created_on = $a->created_on;
                        $a->old_published_on = $a->published_on;
                        $a->old_visibility = $a->visibility;
                        $a->current_slug = $a->slug;
                    } elseif (isset($_POST['from_directory'])) {
                        // Cache this to prevent tag spillage from IPTC
                        $tags_cache = $_POST['tags'];

                        if (is_dir($_POST['from_directory'])) {
                            $_POST['tags'] = '';
                            $this->load->helper('directory', 1);
                            $files = directory_map($_POST['from_directory']);
                            $content_ids = [];
                            foreach ($files as $file) {
                                $c = new Content();
                                $file = $_POST['from_directory'] . DIRECTORY_SEPARATOR . $file;
                                $filename = basename($file);
                                list($internal_id, $path) = $c->generate_internal_id();
                                if (file_exists($file)) {
                                    if ($path) {
                                        $path .= $filename;
                                    } else {
                                        $this->error('500', 'Unable to create directory for upload.');
                                        return;
                                    }
                                    copy($file, $path);
                                    $from = [];
                                    $from['filename'] = $filename;
                                    $from['internal_id'] = $internal_id;
                                    $from['file_modified_on'] = time();
                                    $c->from_array($from, array(), true);
                                    $content_ids[] = $c->id;
                                }
                            }
                        }

                        $_POST['tags'] = $tags_cache;
                    }

                    // Don't allow these fields to be saved generically
                    $private = array('parent_id', 'left_id', 'right_id');

                    if ($a->exists()) {
                        $private[] = 'album_type';
                    }

                    if (isset($_REQUEST['reset_internal_id']) &&
                        $_REQUEST['reset_internal_id'] &&
                        $a->exists()) {
                        array_shift($private);
                        $_POST['internal_id'] = koken_rand();
                    } else {
                        $private[] = 'internal_id';
                    }

                    foreach ($private as $p) {
                        unset($_POST[$p]);
                    }

                    if ($a->has_db_permission('lock tables')) {
                        $s = new Slug();
                        $t = new Tag();
                        $c = new Content();
                        $cat = new Category();
                        $this->db->query("LOCK TABLE {$a->table} WRITE, {$c->table} WRITE, {$s->table} WRITE, {$t->table} WRITE, {$cat->table} WRITE, {$a->db_join_prefix}albums_content READ, {$a->db_join_prefix}albums_categories READ, {$a->db_join_prefix}albums_tags READ");
                        $locked = true;
                    } else {
                        $locked = false;
                    }

                    try {
                        $a->from_array($_POST, array(), true);
                    } catch (Exception $e) {
                        $this->error('400', $e->getMessage());
                        return;
                    }

                    if ($locked) {
                        $this->db->query('UNLOCK TABLES');
                    }

                    $a->repair_tree();

                    if (isset($_POST['tags'])) {
                        $a->_format_tags($_POST['tags']);
                    } elseif ($this->method === 'put' && isset($_POST['visibility'])) {
                        $a->_update_tag_counts();
                    }

                    $arr = $a->to_array();
                    if ($this->method === 'post') {
                        Shutter::hook('album.create', $arr);
                    } else {
                        Shutter::hook('album.update', $arr);
                    }

                    if (isset($content_ids)) {
                        $clean = new Album();
                        $clean = $clean->get_by_id($a->id);
                        $clean->manage_content(join(',', $content_ids), 'post', true);
                    }
                    $this->redirect("/albums/{$a->id}");
                    break;
                case 'delete':
                    if (is_null($id)) {
                        $this->error('403', 'Required parameter "id" not present.');
                        return;
                    } else {
                        $prefix = preg_replace('/albums$/', '', $a->table);

                        if ($id === 'trash') {
                            $id = [];
                            $trash = new Trash();
                            $trash
                                ->like('id', 'album-')
                                ->select_func('REPLACE', '@id', 'album-', '', 'actual_id')
                                ->get_iterated();

                            foreach ($trash as $item) {
                                $id[] = (int) $item->actual_id;
                            }
                        } elseif (is_numeric($id)) {
                            $id = array($id);
                        } else {
                            $id = explode(',', $id);
                        }

                        $tags = [];

                        // Need to loop individually here, otherwise tree can break down
                        foreach ($id as $album_id) {
                            $al = new Album();
                            $al->get_by_id($album_id);

                            if ($al->exists()) {
                                $tags = array_merge($tags, $al->tags);
                                $this->db->query("DELETE FROM {$prefix}trash WHERE id = 'album-{$al->id}'");

                                if ($al->right_id - $al->left_id > 1) {
                                    $children = new Album();
                                    $subs = $children->where('deleted', $al->deleted)
                                                    ->where('visibility', $al->visibility)
                                                    ->where('left_id >', $al->left_id)
                                                    ->where('right_id <', $al->right_id)
                                                    ->where('level >', $al->level)
                                                    ->get_iterated();

                                    foreach ($subs as $sub_album) {
                                        Shutter::hook('album.delete', $sub_album->to_array());
                                        $sub_album->delete();
                                    }
                                }

                                $s = new Slug();
                                $this->db->query("DELETE FROM {$s->table} WHERE id = 'album.{$al->slug}'");

                                Shutter::hook('album.delete', $al->to_array());

                                $al->delete();
                            }
                        }

                        $al->update_set_counts();
                    }
                    exit;
                    break;
            }
        }
        $a = new Album();

        // No id, so we want a list
        if (is_null($id) && !$slug) {
            $final = $a->listing($params);
        }
        // Get album by id
        else {
            $defaults = array(
                            'neighbors' => false,
                            'include_empty_neighbors' => false
                        );
            $options = array_merge($defaults, $params);

            $with_token = false;

            if (is_numeric($id)) {
                $album = $a->where('deleted', 0)->get_by_id($id);
            } else {
                if ($slug) {
                    $album = $a->where('deleted', 0)
                                    ->group_start()
                                        ->where('internal_id', $slug)
                                        ->or_where('slug', $slug)
                                        ->or_like('old_slug', ',' . $slug . ',', 'both')
                                    ->group_end()
                                    ->get();
                } else {
                    $album = $a->where('deleted', 0)
                                    ->where('internal_id', $id)
                                    ->get();
                }

                if ($album->exists() && $album->internal_id === (is_null($id) ? $slug : $id)) {
                    $with_token = true;
                }
            }

            if (!$album->exists()) {
                $this->error('404', 'Album not found.');
                return;
            }

            if ($a->exists()) {
                if ($a->visibility > 0 && !$this->auth && !$with_token) {
                    if ($a->visibility > 1) {
                        // Private content should 404, leave no trace, etc.
                        $this->error('404', 'Album not found.');
                    } else {
                        $this->error('403', 'Private content.');
                    }
                    return;
                }

                $final = $album->to_array($params);
                $final['context'] = $album->context($options, $this->auth);
            } else {
                $this->error('404', "Album with ID: $id not found.");
                return;
            }

            // TODO: This history stuff won't work here anymore
            // if ($this->method == 'put')
            // {
            // 	$h = new History();
            // 	$h->message = array( 'album:update',  $a->title );
            // 	$h->save();
            // }
            // else if ($this->method == 'post')
            // {
            // 	$h = new History();
            // 	$h->message = array( 'album:create',  $a->title );
            // 	$h->save();
            // }
        }
        $this->set_response_data($final);
    }

    public function covers()
    {
       [$params, $id] = $this->parse_params(func_get_args());
        $params['auth'] = $this->auth;

        // Standard add/delete cover
        list($id, $content_id) = $id;

        if ($this->method === 'get') {
            $this->redirect("/albums/$id");
        }

        $a = new Album($id);
        $c = new Content();

        if (!$a->exists()) {
            $this->error('404', 'Album not found.');
            return;
        }

        $cover_count = $a->covers->count();

        if ($cover_count > 50) {
            $this->error('403', 'Only 50 covers can be added to any one album.');
            return;
        }

        if ($a->album_type == 2 && $cover_count == 0) {
            $subs = new Album();
            $subs->select('id')
                ->where('right_id <', $a->right_id)
                ->where('left_id >', $a->left_id)
                ->where('visibility', $a->visibility)
                ->get_iterated();

            $id_arr = [];

            foreach ($subs as $sub) {
                $id_arr[] = $sub->id;
            }

            if (!empty($id_arr)) {
                $subc = new Content();
                $covers = $subc->query("SELECT DISTINCT cover_id FROM {$a->db_join_prefix}albums_covers WHERE album_id IN (" . join(',', $id_arr) . ") GROUP BY album_id LIMIT " . (3 - $cover_count));

                $f_ids = [];
                foreach ($covers as $f) {
                    $f_ids[] = $f->cover_id;
                }

                if (!empty($f_ids)) {
                    $subc->query("SELECT id FROM {$subc->table} WHERE id IN(" . join(',', $f_ids) . ") ORDER BY FIELD(id, " . join(',', array_reverse($f_ids)) . ")");

                    foreach ($subc as $content) {
                        $a->save_cover($content);
                    }
                }
            }
        }

        if (is_numeric($content_id)) {
            if ($this->method == 'delete') {
                $c->where_related('covers', 'id', $id)->get_by_id($content_id);
            } else {
                if ($a->album_type == 2) {
                    $c->get_by_id($content_id);
                } else {
                    $c->where_related('album', 'id', $id)->get_by_id($content_id);
                }
            }

            if (!$c->exists()) {
                $this->error('404', 'Content not found.');
                return;
            }

            if ($this->method == 'delete') {
                $a->delete_cover($c);
                $a->reset_covers();
            } else {
                $a->delete_cover($c);
                $a->save_cover($c);
            }
        } else {
            $content_id = explode(',', $content_id);

            if ($this->method == 'delete') {
                $c->where_related('covers', 'id', $id)->where_in('id', $content_id)->get_iterated();
            } else {
                if ($a->album_type == 2) {
                    $c->where_in('id', $content_id)->get_iterated();
                } else {
                    $c->where_related('album', 'id', $id)->where_in('id', $content_id)->get_iterated();
                }
            }

            if (!$c->result_count()) {
                $this->error('404', 'Content not found.');
                return;
            }

            if ($this->method == 'delete') {
                foreach ($c as $cover) {
                    $a->delete_cover($cover);
                }

                $a->reset_covers();
            } else {
                foreach ($c as $cover) {
                    $a->delete_cover($cover);
                }

                foreach ($content_id as $cid) {
                    $a->save_cover($c->get_by_id($cid));
                }
            }
        }
        $this->redirect("/albums/$id");
    }

    public function content()
    {
        list($params, $id, $slug) = $this->parse_params(func_get_args());
        $params['auth'] = $this->auth;

        $a = new Album();
        $c = new Content();

        if (is_null($id) && !$slug) {
            $this->error('403', 'Required parameter "id" not present.');
            return;
        } elseif (is_array($id)) {
            list($id, $content_id) = $id;
        }

        if ($this->method != 'get') {
            $album = $a->get_by_id($id);

            if (!$album->exists()) {
                $this->error('404', 'Album not found.');
                return;
            }

            $tail = '';

            if (isset($params['order'])) {
                if ($album->album_type == 2) {
                    $this->_order($params['order'], $album);
                } else {
                    $ids = explode(',', $params['order']);
                    $new_order_map = [];

                    foreach ($ids as $key => $val) {
                        $pos = $key + 1;
                        $new_order_map[$val] = $pos;
                    }

                    $album->trans_begin();
                    foreach ($album->contents->include_join_fields()->get_iterated() as $c) {
                        if (isset($new_order_map[$c->id]) && $new_order_map[$c->id] != $c->join_order) {
                            $album->set_join_field($c, 'order', $new_order_map[$c->id]);
                        }
                    }
                    $album->trans_commit();
                }
            } else {
                if (!isset($content_id)) {
                    $this->error('403', 'Required content id not present.');
                    return;
                } elseif ($album->album_type == 1) {
                    $this->error('403', 'You cannot manually add content to smart albums.');
                    return;
                }
                if ($id == $content_id && $album->album_type == 2) {
                    $this->error('403', 'Album cannot be added to itself.');
                    return;
                }
                $album->manage_content($content_id, $this->method, $this->input->post('match_album_visibility'));
            }

            $repair = new Album();
            $repair->repair_tree();

            if ($this->method == 'delete') {
                exit;
            } else {
                $this->redirect("/albums/{$album->id}/content");
                exit;
            }
        }

        $with_token = false;

        if (is_numeric($id)) {
            $album = $a->where('deleted', 0)->get_by_id($id);
        } else {
            if ($slug) {
                $album = $a->where('deleted', 0)
                                ->group_start()
                                    ->where('internal_id', $slug)
                                    ->or_where('slug', $slug)
                                    ->or_like('old_slug', ',' . $slug . ',', 'both')
                                ->group_end()
                                ->get();
            } else {
                $album = $a->where('deleted', 0)
                                ->where('internal_id', $id)
                                ->get();
            }

            if ($album->exists() && $album->internal_id === (is_null($id) ? $slug : $id)) {
                $with_token = true;
            }
        }

        if ($album->exists()) {
            if ($album->visibility > 0 && !$this->auth && !$with_token) {
                if ($album->visibility > 1) {
                    // Private content should 404, leave no trace, etc.
                    $this->error('404', 'Album not found.');
                } else {
                    $this->error('403', 'Private content.');
                }
                return;
            }
        } else {
            $this->error('404', 'Album not found.');
            return;
        }

        $order = explode(' ', $album->sort);

        if (!isset($params['order_by'])) {
            $params['order_by'] = $order[0];
            if (count($order) > 1) {
                $params['order_direction'] = $order[1];
            }
        }

        if ($album->album_type == 2) {
            $options = array(
                'neighbors' => false,
                'include_empty_neighbors' => false,
                'order_by' => 'manual',
                'with_context' => true
            );
            $params = array_merge($options, $params);
            if ($params['order_by'] === 'manual') {
                $params['order_by'] = 'left_id';
                $params['order_direction'] = 'asc';
            }

            $final = $album->listing($params);
        } else {
            $options = array(
                'order_by' => 'manual',
                'covers' => null,
                'neighbors' => false,
                'include_empty_neighbors' => false,
                'in_album' => $album,
                'with_context' => true,
                'is_cover' => true,
                'visibility' => 'any',
            );

            if (!isset($params['visibility']) && $with_token) {
                $params['visibility'] = 'album';
            }

            $params = array_merge($options, $params);

            $params['auth'] = $this->auth || $with_token;

            if ($params['covers']) {
                if ($params['order_by'] === 'manual') {
                    $params['order_by'] = 'cover_id';
                }
                $c = $album->covers;
            } else {
                $c->where('deleted', 0);
                if (!is_null($params['covers'])) {
                    $cids = [];
                    foreach ($album->covers->get_iterated() as $cover) {
                        $cids[] = $cover->id;
                    }
                    $c->where_not_in('id', $cids);
                }
                $c->where_related_album('id', $album->id);

                if ($params['order_by'] === 'manual') {
                    $params['order_by'] = 'order';
                    $params['order_direction'] = 'asc';
                }
            }

            $final = $c->listing($params);
        }
        $params['include_parent'] = true;
        unset($params['category']);
        unset($params['tags']);
        $final['album'] = $album->to_array($params);

        if (isset($params['context_set']) && $album->level > 1) {
            $parent = new Album();
            $parent->where('left_id <', $album->left_id)
                    ->where('level <', $album->level)
                    ->where('visibility', $album->visibility)
                    ->where('deleted', 0)
                    ->order_by('left_id DESC')
                    ->limit(1)
                    ->get();

            list($params['context_order'], $params['context_order_direction']) = explode(' ', $parent->sort);
        }

        if (isset($final['album']['covers']) && !empty($final['album']['covers']) && isset($final['content'])) {
            $covers = [];
            foreach ($final['album']['covers'] as $cover) {
                $covers[] = $cover['id'];
            }

            $primary = false;
            foreach ($final['content'] as &$c) {
                $c['is_cover'] = in_array($c['id'], $covers);
                if ($c['is_cover'] && !$primary) {
                    $c['is_primary_cover'] = true;
                    $primary = true;
                } else {
                    $c['is_primary_cover'] = false;
                }
            }
        }

        if ($params['with_context']) {
            $final['album']['context'] = $album->context($params, $this->auth);
        }
        $this->set_response_data($final);
    }
}

/* End of file albums.php */
/* Location: ./system/application/controllers/albums.php */
