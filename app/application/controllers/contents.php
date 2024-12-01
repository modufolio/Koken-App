<?php

class Contents extends Koken_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function cache()
    {
       [$params, $id] = $this->parse_params(func_get_args());

        if ($this->method === 'delete') {
            $c = new Content();
            $c->update('file_modified_on', time());

            Shutter::clear_cache('images');
            Shutter::clear_cache('albums');
            Shutter::clear_cache('icc');
        } elseif ($this->method === 'post') {
            $page = isset($params['page']) ? (int) $params['page'] : 1;

            $c = new Content();
            $contents = $c->get_paged_iterated($page, 1);

            if (extension_loaded('curl')) {
                $presets = ['medium', 'medium_large', 'large', 'xlarge'];
                $options = [CURLOPT_HTTPHEADER => ['Connection: Close', 'Keep-Alive: 0'], CURLOPT_HEADER => 0, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_RETURNTRANSFER => 1, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_SSL_VERIFYPEER => false];

                foreach ($contents as $c) {
                    $content = $c->to_array();

                    foreach ($presets as $preset) {
                        $curl = curl_init();
                        curl_setopt_array($curl, $options);
                        curl_setopt($curl, CURLOPT_URL, $content['presets'][$preset]['url']);
                        $r = curl_exec($curl);
                        curl_close($curl);

                        if (preg_match('/^.?large$/', $preset) === 0) {
                            $curl = curl_init();
                            curl_setopt_array($curl, $options);
                            curl_setopt($curl, CURLOPT_URL, $content['presets'][$preset]['cropped']['url']);
                            $r = curl_exec($curl);
                            curl_close($curl);
                        }
                    }
                }

                die(json_encode(['total_pages' => $contents->paged->total_pages, 'next_page' => $contents->paged->has_next ? $contents->paged->next_page : false]));
            }
        }

        exit;
    }

    public function albums()
    {
       [$params, $id] = $this->parse_params(func_get_args());
        $a = new Album();

        if (isset($params['context'])) {
            $a->where('id !=', $params['context']);
        }

        $params['auth'] = $this->auth;
        $params['flat'] = true;

        $final = $a->where_related('content', 'id', $id)->listing($params);
        $this->set_response_data($final);
    }

    public function categories()
    {
       [$params, $id] = $this->parse_params(func_get_args());
        $c = new Category();

        $params['auth'] = $this->auth;
        $params['limit_to'] = 'content';

        if (!str_contains((string) $id, ',')) {
            $final = $c->where_related('content', 'id', $id)->listing($params);
        } else {
            $final = $c->get_grouped_status(explode(',', (string) $id), 'Content');
        }

        $this->set_response_data($final);
    }

    public function index()
    {
        [$params, $id, $slug] = $this->parse_params(func_get_args());

        // Create or update
        if ($this->method != 'get') {
            $c = new Content();
            switch ($this->method) {
                case 'post':
                case 'put':
                    if ($this->method == 'put') {
                        // Update
                        $c->get_by_id($id);
                        if (!$c->exists()) {
                            $this->error('404', "Content with ID: $id not found.");
                            return;
                        }

                        $c->old_published_on = $c->published_on;
                        $c->old_captured_on = $c->captured_on;
                        $c->old_uploaded_on = $c->uploaded_on;
                        if (isset($_POST['slug'])) {
                            $c->current_slug = $c->slug;
                        }
                    }

                    if (isset($_REQUEST['name'])) {
                        if (isset($_REQUEST['upload_session_start'])) {
                            $s = new Setting();
                            $s->where('name', 'last_upload')->get();
                            if ($s->exists() && $s->value != $_REQUEST['upload_session_start']) {
                                $s->value = $_REQUEST['upload_session_start'];
                                $s->save();
                            }
                        }

                        $file_name = $c->clean_filename($_REQUEST['name']);

                        $chunk = $_REQUEST["chunk"] ?? 0;
                        $chunks = $_REQUEST["chunks"] ?? 0;

                        $tmp_dir = FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
                        $tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $file_name;

                        make_child_dir($tmp_dir);

                        if ($chunks == 0 || $chunk == ($chunks - 1)) {
                            if (isset($_REQUEST['text'])) {
                                $path = FCPATH . 'storage' .
                                        DIRECTORY_SEPARATOR . 'custom' .
                                        DIRECTORY_SEPARATOR;
                                $internal_id = false;
                            } elseif (isset($_REQUEST['plugin'])) {
                                $info = pathinfo((string) $_REQUEST['name']);
                                $path = FCPATH . 'storage' .
                                        DIRECTORY_SEPARATOR . 'plugins' .
                                        DIRECTORY_SEPARATOR . $_REQUEST['plugin'] .
                                        DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR;
                                $file_name = $_REQUEST['basename'] . '.' . $info['extension'];
                                $internal_id = false;
                            } else {
                                [$internal_id, $path] = $c->generate_internal_id();
                            }
                            if ($path) {
                                $path .= $file_name;
                                if ($chunks == 0) {
                                    $tmp_path = $path;
                                }
                            } else {
                                $this->error('500', 'Unable to create directory for upload.');
                                return;
                            }
                        }

                        // Look for the content type header
                        if (isset($_SERVER["HTTP_CONTENT_TYPE"])) {
                            $contentType = $_SERVER["HTTP_CONTENT_TYPE"];
                        } elseif (isset($_SERVER["CONTENT_TYPE"])) {
                            $contentType = $_SERVER["CONTENT_TYPE"];
                        } else {
                            $contentType = '';
                        }

                        if (str_contains((string) $contentType, "multipart")) {
                            if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                                $out = fopen($tmp_path, $chunk == 0 ? "wb" : "ab");
                                if ($out) {
                                    // Read binary input stream and append it to temp file
                                    $in = fopen($_FILES['file']['tmp_name'], "rb");

                                    if ($in) {
                                        while ($buff = fread($in, 4096)) {
                                            fwrite($out, $buff);
                                        }
                                    } else {
                                        $this->error('500', 'Unable to read input stream.');
                                        return;
                                    }

                                    fclose($out);
                                    unlink($_FILES['file']['tmp_name']);
                                } else {
                                    $this->error('500', 'Unable to write to output file.');
                                    return;
                                }
                            } else {
                                $this->error('500', 'Unable to move uploaded file.');
                                return;
                            }
                        } else {
                            $out = fopen($tmp_path, $chunk == 0 ? "wb" : "ab");
                            if ($out) {
                                // Read binary input stream and append it to temp file
                                $in = fopen("php://input", "rb");

                                if ($in) {
                                    while ($buff = fread($in, 4096)) {
                                        fwrite($out, $buff);
                                    }
                                } else {
                                    $this->error('500', 'Unable to read uploaded file.');
                                    return;
                                }
                                fclose($out);
                            } else {
                                $this->error('500', 'Unable to open output stream.');
                                return;
                            }
                        }

                        if ($chunk < ($chunks - 1)) {
                            // Don't continue until all chunks are uploaded
                            exit;
                        } elseif ($chunks > 0) {
                            // Done, move to permanent location and save to DB
                            rename($tmp_path, $path);
                        }

                        if (!$internal_id) {
                            // Custom text uploads can stop here
                            die(json_encode(['filename' => $file_name]));
                        }

                        $from = [];
                        $from['filename'] = $file_name;
                        $from['internal_id'] = $internal_id;
                        $from['file_modified_on'] = time();
                    } elseif (isset($_POST['localfile'])) {
                        $filename = basename((string) $_REQUEST['localfile']);
                        [$internal_id, $path] = $c->generate_internal_id();
                        if (!file_exists($_REQUEST['localfile'])) {
                            $this->error('500', '"localfile" does not exist.');
                            return;
                        }
                        if ($path) {
                            $path .= $filename;
                        } else {
                            $this->error('500', 'Unable to create directory for upload.');
                            return;
                        }
                        copy($_REQUEST['localfile'], $path);
                        $from = [];
                        $from['filename'] = $filename;
                        $from['internal_id'] = $internal_id;
                        $from['file_modified_on'] = time();
                    } elseif (isset($_POST['from_url'])) {
                        $filename = basename((string) $_POST['from_url']);
                        [$internal_id, $path] = $c->generate_internal_id();
                        if ($path) {
                            $path .= $filename;
                        } else {
                            $this->error('500', 'Unable to create directory for upload.');
                            return;
                        }
                        if ($this->_download(urldecode((string) $_POST['from_url']), $path, true) && file_exists($path)) {
                            $from = [];
                            $from['filename'] = $filename;
                            $from['internal_id'] = $internal_id;
                            $from['file_modified_on'] = time();
                        } else {
                            $this->error('500', 'Unable to import file from provided URL.');
                            return;
                        }
                    } elseif (is_null($id)) {
                        $this->error('403', 'New content records must be accompanied by an upload.');
                        return;
                    }

                    if (isset($from)) {
                        $from = array_merge($_POST, $from);
                    } else {
                        $from = $_POST;
                    }

                    if (isset($_REQUEST['rotate']) &&
                        is_numeric($_REQUEST['rotate']) &&
                        $c->exists()) {
                        $r = $_REQUEST['rotate'];
                        if (abs($r) != 90) {
                            $this->error('403', 'Rotation can only be done in multiples of 90.');
                            return;
                        }

                        if (empty($c->storage_url)) {
                            $path = $c->path_to_original();

                            $info = pathinfo((string) $path);
                            $midsize_path = preg_replace('/\.' . $info['extension'] . '$/', '.1600.' . $info['extension'], (string) $path);
                            if (file_exists($midsize_path)) {
                                $midsize = $midsize_path;
                            }
                        } else {
                            $path = tempnam(sys_get_temp_dir(), 'original');
                            file_put_contents($path, file_get_contents($c->storage_url));
                            if (!empty($c->storage_url_midsize)) {
                                $midsize = tempnam(sys_get_temp_dir(), 'midsize');
                                file_put_contents($midsize, file_get_contents($c->storage_url_midsize));
                            }
                        }

                        $s = new Setting();
                        $s->where('name', 'image_processing_library')->get();

                        include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'DarkroomUtils.php');

                        $d = DarkroomUtils::init($s->value);

                        $d->rotate($path, $r);

                        if (isset($midsize)) {
                            $d->rotate($midsize, $r);
                        }

                        if (!empty($c->storage_url)) {
                            $key = $c->path . '/' . $c->filename;
                            Shutter::store_original($path, $c->path . '/' . $c->filename);

                            unlink($path);
                            if (isset($midsize)) {
                                $info = pathinfo($key);
                                $key = preg_replace('/\.' . $info['extension'] . '$/', '.1600.' . $info['extension'], $key);
                                Shutter::store_original($midsize, $key);
                                unlink($midsize);
                            }
                        }

                        $c->clear_cache();
                        $from['width'] = $c->height;
                        $from['height'] = $c->width;
                        $from['aspect_ratio'] = $from['width'] / $from['height'];
                        $from['file_modified_on'] = time();
                    }

                    if (isset($_REQUEST['reset_internal_id']) &&
                        $_REQUEST['reset_internal_id'] &&
                        $c->exists()) {
                        [$from['internal_id'], ] = $c->generate_internal_id(true);
                    }

                    $hook = 'content.' . ($id ? 'update' : 'create');

                    if (isset($from['filename']) && $id) {
                        $c->clear_cache();
                        $hook .= '_with_upload';
                        $c->_before();
                    }

                    if (isset($from['max_download']) && $id) {
                        $a = new Album();
                        $albums = $a->where_related('content', 'id', $id)->get_iterated();
                        $start = strtotime(gmdate("M d Y H:i:s", time()));
                        foreach ($albums as $a) {
                            $a->update(['modified_on' => $start], false);
                        }
                    }

                    $from = Shutter::filter("api.$hook", array_merge($from, ['id' => $id, 'file' => $path ?? $c->path_to_original()]));

                    unset($from['file']);

                    try {
                        $c->from_array($from, [], true);
                    } catch (Exception $e) {
                        $this->error('400', $e->getMessage());
                        return;
                    }

                    if (isset($_POST['tags'])) {
                        $c->_format_tags($_POST['tags']);
                    } elseif ($this->method === 'put' && isset($_POST['visibility'])) {
                        $c->_update_tag_counts();
                    }

                    $c->_readify();

                    $content = $c->to_array(['auth' => true]);

                    if (($hook === 'content.create' || $hook === 'content.update_with_upload')) {
                        $external_storage_url = Shutter::store_original($c->path_to_original(), str_replace('/storage/originals/', '', $content['original']['relative_url']));

                        if ($external_storage_url) {
                            unlink($c->path_to_original());

                            $o = new Content();
                            $o->where('id', $content['id'])
                                ->update(['storage_url' => $external_storage_url]);

                            $content['storage_url'] = $external_storage_url;
                        }
                    }

                    Shutter::hook($hook, $content);

                    // Important to prevent failures from Lr plugin
                    header('Connection: close');

                    $this->redirect("/content/{$c->id}" . (isset($params['context']) ? '/context:' . $params['context'] : ''));
                    break;
                case 'delete':
                    if (is_null($id)) {
                        $this->error('403', 'Required parameter "id" not present.');
                        return;
                    } else {
                        $t = new Tag();
                        if (is_numeric($id)) {
                            $content = $c->get_by_id($id);
                            if ($c->exists()) {
                                $trash = new Trash();
                                $this->db->query("DELETE from {$trash->table} WHERE id = 'content-{$c->id}'");
                                $c->do_delete();
                            } else {
                                $this->error('404', "Content with ID: $id not found.");
                                return;
                            }
                        } else {
                            $is_trash = $id === 'trash';

                            if ($id === 'trash') {
                                $id = [];
                                $trash = new Trash();
                                $trash
                                    ->like('id', 'content-')
                                    ->select_func('REPLACE', '@id', 'content-', '', 'actual_id')
                                    ->get_iterated();

                                foreach ($trash as $item) {
                                    $id[] = (int) $item->actual_id;
                                }
                            } else {
                                $id = explode(',', (string) $id);
                            }

                            /*
                                Multiple delete
                                 /content/n1/n2/n3
                            */


                            $c->where_in('id', $id);
                            $contents = $c->get_iterated();
                            $trash = new Trash();
                            foreach ($contents as $c) {
                                if ($c->exists()) {
                                    $this->db->query("DELETE from {$trash->table} WHERE id = 'content-{$c->id}'");
                                    $c->do_delete();
                                }
                            }
                        }
                    }
                    exit;
                    break;
            }
        }
        $c = new Content();
        if ($slug || (isset($id) && !str_contains((string) $id, ','))) {
            $options = ['context' => false, 'neighbors' => false];
            $options = array_merge($options, $params);

            $original_context = $options['context'];

            if ($options['context'] && !in_array($options['context'], ['stream', 'favorites', 'features']) && !str_starts_with((string) $options['context'], 'tag-') && !str_starts_with((string) $options['context'], 'category-')) {
                if (is_numeric($options['context'])) {
                    $context_field = 'id';
                } else {
                    $context_field = 'slug';
                    $options['context'] = str_replace('slug-', '', $options['context']);
                }

                $a = new Album();
                $a->group_start()
                        ->where($context_field, $options['context'])
                        ->or_where('internal_id', $options['context'])
                    ->group_end()
                    ->get();

                $c->include_join_fields()->where_related_album('id', $a->id);
            }

            $with_token = false;

            if (is_numeric($id)) {
                $content = $c->where('deleted', 0)->get_by_id($id);
            } else {
                if ($slug) {
                    $content = $c->where('deleted', 0)
                                    ->group_start()
                                        ->where('internal_id', $slug)
                                        ->or_where('slug', $slug)
                                        ->or_like('old_slug', ',' . $slug . ',', 'both')
                                    ->group_end()
                                    ->get();
                } else {
                    $content = $c->where('deleted', 0)
                                    ->where('internal_id', $id)
                                    ->get();
                }

                if ($content->exists() && $content->internal_id === (is_null($id) ? $slug : $id)) {
                    $with_token = true;
                }
            }

            if ($content->exists()) {
                if (($c->visibility == 1 && !$this->auth && !$with_token) || (!$this->auth && !is_numeric($id) && $c->visibility == 2)) {
                    $this->error('403', 'Private content.');
                    return;
                }

                $options['auth'] = $this->auth;

                if ($options['neighbors']) {
                    // Make sure $neighbors is at least 2
                    $options['neighbors'] = max($options['neighbors'], 2);

                    // Make sure neighbors is even
                    if ($options['neighbors'] & 1 != 0) {
                        $options['neighbors']++;
                    }

                    $options['neighbors'] = $options['neighbors']/2;
                    $single_neighbors = false;
                } else {
                    $options['neighbors'] = 1;
                    $single_neighbors = true;
                }

                if ($options['context'] && !in_array($original_context, ['stream', 'favorites', 'features']) && !str_starts_with((string) $original_context, 'tag-') && !str_starts_with((string) $original_context, 'category-')) {
                    $options['in_album'] = $a;
                }

                $final = $content->to_array($options);

                if ($options['context']) {
                    // TODO: Performance check
                    $next = new Content();
                    $prev = new Content();
                    $in_a = new Album();

                    $next->where('deleted', 0);
                    $prev->where('deleted', 0);

                    $options['context'] = urldecode((string) $options['context']);

                    if (!in_array($original_context, ['stream', 'favorites', 'features']) && !str_starts_with((string) $original_context, 'tag-') && !str_starts_with((string) $original_context, 'category-')) {
                        if (!isset($options['context_order'])) {
                            [$options['context_order'], $options['context_order_direction']] = explode(' ', $a->sort);
                        }

                        $final['context']['album'] = $a->to_array(['auth' => $this->auth || $options['context'] === $a->internal_id]);

                        $in_a->where("$context_field !=", $options['context']);

                        $next->where_related_album('id', $a->id);
                        $prev->where_related_album('id', $a->id);

                        if ($options['context_order'] === 'manual') {
                            $next->order_by_join_field('album', 'order', 'ASC')
                                ->group_start()
                                    ->where_join_field('album', 'order >', $content->join_order)
                                    ->or_group_start()
                                        ->where_join_field('album', 'order', $content->join_order)
                                        ->where_join_field('album', 'id >', $content->join_id)
                                    ->group_end()
                                ->group_end();

                            $prev->order_by_join_field('album', 'order', 'DESC')
                                ->group_start()
                                    ->where_join_field('album', 'order <', $content->join_order)
                                    ->or_group_start()
                                        ->where_join_field('album', 'order', $content->join_order)
                                        ->where_join_field('album', 'id <', $content->join_id)
                                    ->group_end()
                                ->group_end();
                        } else {
                            $next_operator = strtolower((string) $options['context_order_direction']) === 'desc' ? '<' : '>';
                            $prev_operator = $next_operator === '<' ? '>' : '<';
                            $val = $options['context_order'] === 'title' && is_null($content->{$options['context_order']}) ? '' : $content->{$options['context_order']};

                            $next
                                ->group_start()
                                    ->where($options['context_order'] . " $next_operator", $val)
                                    ->or_group_start()
                                        ->where($options['context_order'], $content->{$options['context_order']})
                                        ->where("id $next_operator", $content->id)
                                    ->group_end()
                                ->group_end();

                            $prev
                                ->group_start()
                                    ->where($options['context_order'] . " $prev_operator", $val)
                                    ->or_group_start()
                                        ->where($options['context_order'], $content->{$options['context_order']})
                                        ->where("id $prev_operator", $content->id)
                                    ->group_end()
                                ->group_end();
                        }

                        if (!$this->auth) {
                            $next->where('visibility <', $final['context']['album']['visibility']['raw'] === 'public' ? 1 : 2);
                            $prev->where('visibility <', $final['context']['album']['visibility']['raw'] === 'public' ? 1 : 2);
                        }

                        $in_album = $a;

                        $final['context']['type'] = 'album';
                        $final['context']['title'] = $a->title;
                        $final['context']['__koken_url'] = $final['context']['album']['__koken_url'];
                        $final['context']['url'] = $final['context']['album']['url'];
                    } else {
                        if (empty($options['context_order'])) {
                            $options['context_order'] = 'captured_on';
                            $options['context_order_direction'] = 'DESC';
                        } elseif ($options['context_order'] === 'manual' && $original_context === 'favorites') {
                            $options['context_order'] = 'favorite_order';
                            $options['context_order_direction'] = 'ASC';
                        } elseif ($options['context_order'] === 'manual' && $original_context === 'features') {
                            $options['context_order'] = 'featured_order';
                            $options['context_order_direction'] = 'ASC';
                        }

                        $next_operator = strtolower((string) $options['context_order_direction']) === 'desc' ? '<' : '>';
                        $prev_operator = $next_operator === '<' ? '>' : '<';

                        $next
                            ->group_start()
                                ->where($options['context_order'] . " $next_operator", $content->{$options['context_order']})
                                ->or_group_start()
                                    ->where($options['context_order'], $content->{$options['context_order']})
                                    ->where("id $next_operator", $content->id)
                                ->group_end()
                            ->group_end();

                        $prev
                            ->group_start()
                                ->where($options['context_order'] . " $prev_operator", $content->{$options['context_order']})
                                ->or_group_start()
                                    ->where($options['context_order'], $content->{$options['context_order']})
                                    ->where("id $prev_operator", $content->id)
                                ->group_end()
                            ->group_end();

                        if (str_starts_with((string) $original_context, 'tag-')) {
                            $tag = str_replace('tag-', '', urldecode((string) $original_context));
                            $t = new Tag();
                            $t->where('name', $tag)->get();
                            if ($t->exists()) {
                                $next->where_related_tag('id', $t->id);
                                $prev->where_related_tag('id', $t->id);
                                $final['context']['type'] = 'tag';
                                $final['context']['title'] = $tag;
                                $final['context']['slug'] = $tag;

                                $t->model = 'tag_contents';
                                $t->slug = $t->name;
                                $url = $t->url();

                                if ($url) {
                                    [$final['context']['__koken_url'], $final['context']['url']] = $url;
                                }
                            }
                        } elseif (str_starts_with((string) $original_context, 'category-')) {
                            $category = str_replace('category-', '', $original_context);
                            $cat = new Category();
                            $cat->where('slug', $category)->get();
                            if ($cat->exists()) {
                                $next->where_related_category('id', $cat->id);
                                $prev->where_related_category('id', $cat->id);
                                $final['context']['type'] = 'category';
                                $final['context']['title'] = $cat->title;
                                $final['context']['slug'] = $cat->slug;

                                $cat->model = 'category_contents';
                                $url = $cat->url();

                                if ($url) {
                                    [$final['context']['__koken_url'], $final['context']['url']] = $url;
                                }
                            }
                        } elseif ($original_context === 'favorites') {
                            $url_data = $prev->get_data();
                            $urls = $prev->form_urls();
                            $next->where('favorite', 1);
                            $prev->where('favorite', 1);
                            $final['context']['type'] = 'favorite';
                            $final['context']['title'] = 'language.favorites';
                            $final['context']['__koken_url'] = $urls['favorites'];

                            if ($final['context']['__koken_url']) {
                                $final['context']['url'] = $prev->get_base() . $final['context']['__koken_url'] . (defined('DRAFT_CONTEXT') && !is_numeric(DRAFT_CONTEXT) ? '&preview=' . DRAFT_CONTEXT : '');
                            }
                        } elseif ($original_context === 'features') {
                            $url_data = $prev->get_data();
                            $urls = $prev->form_urls();
                            $next->where('featured', 1);
                            $prev->where('featured', 1);
                            $final['context']['type'] = 'feature';
                            $final['context']['title'] = 'language.features';
                            $final['context']['__koken_url'] = $urls['features'] ?? false;

                            if ($final['context']['__koken_url']) {
                                $final['context']['url'] = $prev->get_base() . $final['context']['__koken_url'] . (defined('DRAFT_CONTEXT') && !is_numeric(DRAFT_CONTEXT) ? '&preview=' . DRAFT_CONTEXT : '');
                            }
                        }

                        if (!$this->auth) {
                            $next->where('visibility', 0);
                            $prev->where('visibility', 0);
                        }

                        $in_album = false;
                    }

                    $max = $next->get_clone()->count();
                    $min = $prev->get_clone()->count();
                    $final['context']['total'] = $max + $min + 1;
                    $final['context']['position'] = $min + 1;
                    $pre_limit = $next_limit = $options['neighbors'];

                    if ($min < $pre_limit) {
                        $next_limit += ($pre_limit - $min);
                        $pre_limit = $min;
                    }
                    if ($max < $next_limit) {
                        $pre_limit = min($min, $pre_limit + ($next_limit - $max));
                        $next_limit = $max;
                    }

                    $final['context']['previous'] = [];
                    $final['context']['next'] = [];

                    if ($next_limit > 0) {
                        if ($options['context_order'] !== 'manual') {
                            $next->order_by($options['context_order'] . ' ' . $options['context_order_direction'] . ', id ' . $options['context_order_direction']);
                        }

                        $next->limit($next_limit)->get_iterated();

                        foreach ($next as $c) {
                            $final['context']['next'][] = $c->to_array(['auth' => $this->auth, 'in_album' => $in_album, 'context' => $original_context]);
                        }
                    }

                    if ($pre_limit > 0) {
                        if ($options['context_order'] !== 'manual') {
                            $dir = strtolower((string) $options['context_order_direction']) === 'desc' ? 'asc' : 'desc';
                            $prev->order_by($options['context_order'] . ' ' . $dir . ', id ' . $dir);
                        }

                        $prev->limit($pre_limit)->get_iterated();

                        foreach ($prev as $c) {
                            $final['context']['previous'][] = $c->to_array(['auth' => $this->auth, 'in_album' => $in_album, 'context' => $original_context]);
                        }
                        $final['context']['previous'] = array_reverse($final['context']['previous']);
                    }
                }
            } else {
                $this->error('404', "Content with ID: $id not found.");
                return;
            }
        } elseif (isset($params['custom'])) {
            $final = $c->to_array_custom($params['custom']);
        } else {
            $c->where('deleted', 0);
            $params['auth'] = $this->auth;
            $final = $c->listing($params, $id);
        }

        $this->set_response_data($final);
    }
}

/* End of file contents.php */
/* Location: ./system/application/controllers/contents.php */
