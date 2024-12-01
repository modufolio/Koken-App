<?php

class Content extends Koken
{
    public string $table = 'content';
    public string $created_field = 'uploaded_on';

    public array $validation = ['internal_id' => ['label' => 'Internal id', 'rules' => ['required']], 'uploaded_on' => ['rules' => ['validate_time']], 'published_on' => ['rules' => ['validate_time']], 'captured_on' => ['rules' => ['validate_time']], 'filename' => ['rules' => ['before'], 'get_rules' => ['readify']], 'title' => ['rules' => ['title_to_slug']], 'license' => ['rules' => ['validate_license']], 'visibility' => ['rules' => ['validate_visibility']], 'max_download' => ['rules' => ['validate_max_download']], 'focal_point' => ['rules' => ['validate_focal_point']], 'slug' => ['rules' => ['slug', 'required']]];

    public $exif_cache = [];

    public function _validate_focal_point()
    {
        $this->clear_cache();
        $this->file_modified_on = time();
    }

    public function clean_filename($file)
    {
        $this->load->helper(['url', 'text', 'string']);
        $info = pathinfo((string) $file);
        return reduce_multiples(
            str_replace(
                '_',
                '-',
                url_title(
                    convert_accented_characters($info['filename']),
                    'dash'
                )
            ),
            '-',
            true
        ) . '.' . $info['extension'];
    }

    public function _title_to_slug($field)
    {
        if (empty($this->old_slug) && $this->id) {
            $this->_slug('slug');
        }
    }

    public function _slug($field)
    {
        if ($this->edit_slug()) {
            return true;
        }

        if (!empty($this->old_slug)) {
            return true;
        }

        $this->load->helper(['url', 'text', 'string']);

        if (empty($this->title)) {
            $info = pathinfo($this->filename);
            $base = $info['filename'];
        } else {
            $base = $this->title;
        }

        $slug = reduce_multiples(
            strtolower(
                        (string) url_title(
                            convert_accented_characters($base),
                            'dash'
                        )
                    ),
            '-',
            true
        );

        if ($slug === $this->slug) {
            return true;
        }

        if (empty($slug)) {
            $t = new Content();
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

        while ($s->where('id', "content.$slug")->count() > 0) {
            $slug = increment_string($slug, '-');
        }

        $this->db->query("INSERT INTO {$s->table}(id) VALUES ('content.$slug')");

        if ($locked) {
            $this->db->query('UNLOCK TABLES');
        }

        if (empty($this->old_slug)) {
            if (!empty($this->slug) && $this->slug !== '__generate__') {
                $this->old_slug = ',' . $this->slug . ',';
            } elseif (!empty($this->title)) {
                $this->old_slug = ',' . $slug . ',';
            }
        }

        $this->slug = $slug;
    }

    // Multibyte safe unserialization for cray cray EXIF/IPTC junk
    public function _unserialize($string)
    {
        $original = MB_ENABLED ? mb_convert_encoding($string, 'ISO-8859-1') : mb_convert_encoding($string, 'ISO-8859-1');
        $string = preg_replace_callback(
            '!s:(\d+):"(.*?)";!s',
            fn($matches) => "s:" . strlen((string) $matches[2]) . ":\"" . $matches[2] . "\";",
            $original
        );
        $mb = @unserialize($string);
        $original = @unserialize($original);
        if ($mb && json_encode($mb)) {
            return $mb;
        } elseif (json_encode($original)) {
            return $original;
        } else {
            return [];
        }
    }

    public function _validate_max_download()
    {
        $values = ['none', 'original', 'huge', 'xlarge', 'large', 'medium_large', 'medium'];
        if (in_array($this->max_download, $values)) {
            $this->max_download = array_search($this->max_download, $values);
        } else {
            return false;
        }
    }

    public function _validate_visibility()
    {
        $values = ['public', 'unlisted', 'private'];
        if (in_array($this->visibility, $values)) {
            if ($this->id) {
                $a = new Album();
                $albums = $a->where_related('content', 'id', $this->id)->get_iterated();
                foreach ($albums as $album) {
                    if ($this->visibility === 'public') {
                        $album->reset_covers($this->id);
                    }
                    $album->update_counts(true, ['id' => $this->id, 'visibility' => $this->visibility]);
                }

                if ($this->visibility !== 'public') {
                    $covers = $a->where_related('cover', 'id', $this->id)->get_iterated();
                    foreach ($covers as $album) {
                        $album->delete_cover($this);
                        $album->reset_covers(null, $this->id);
                    }
                }

                if ($this->visibility === 'public') {
                    $this->published_on = null;
                }
            }

            $this->visibility = array_search($this->visibility, $values);
        } else {
            return false;
        }
    }

    public function _validate_license()
    {
        if (!empty($this->license)) {
            if ($this->license == 'all') {
                return true;
            }
            if (!preg_match('/^(y|n)\,(y|s|n)$/', $this->license)) {
                return false;
            }
        }
    }

    public function generate_internal_id($reset = false)
    {
        $base = FCPATH .
                    DIRECTORY_SEPARATOR . 'storage' .
                    DIRECTORY_SEPARATOR . 'originals' .
                    DIRECTORY_SEPARATOR;

        if ($this->exists()) {
            if ($reset) {
                $internal_id = substr($this->internal_id, 0, 4) . substr((string) koken_rand(), 4);
            } else {
                $internal_id = $this->internal_id;
            }
            $path = $base . $this->path;
        } else {
            $internal_id = koken_rand();
            $hash = substr((string) $internal_id, 0, 2) . DIRECTORY_SEPARATOR . substr((string) $internal_id, 2, 2);
            $path = $base . $hash;
            if (!make_child_dir($path)) {
                $path = false;
            }
        }
        return [$internal_id, $path . DIRECTORY_SEPARATOR];
    }

    public function _force_utf($string)
    {
        if (!MB_ENABLED) {
            return $string;
        }

        if (mb_detect_encoding((string) $string) !== 'UTF-8') {
            return mb_convert_encoding($string, 'UTF-8');
        }

        return $string;
    }

    public function _get_iptc_data($path = false)
    {
        if (!$path) {
            $path = $this->path_to_original();
        }

        getimagesize($path, $info);
        if (!empty($info['APP13'])) {
            return iptcparse($info['APP13']);
        }

        return [];
    }

    public function _get_exif_data($path = false)
    {
        if (!isset($this->exif_cache[$this->id])) {
            if (!$path) {
                $path = $this->path_to_original();
            }

            $pathinfo = pathinfo((string) $path);
            if (in_array(strtolower($pathinfo['extension']), ['jpg', 'jpeg']) && is_callable('exif_read_data')) {
                @$this->exif_cache[$this->id] = exif_read_data($path, 0, true);
            } else {
                $this->exif_cache[$this->id] = [];
            }
        }

        return $this->exif_cache[$this->id];
    }

    public function _before()
    {
        if (!preg_match('~^https?://~', $this->filename)) {
            $this->file_type = (int) $this->set_type();
            $path = $this->path_to_original();
            $pathinfo = pathinfo((string) $path);

            if ($this->file_type > 0) {
                include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'ffmpeg.php');
                $ffmpeg = new FFmpeg($path);
                if ($ffmpeg->version()) {
                    $this->duration = $ffmpeg->duration();
                    [$this->width, $this->height] = $ffmpeg->dimensions();
                    $this->lg_preview = $ffmpeg->create_thumbs();
                }
            } else {
                [$this->width, $this->height] = getimagesize($path);

                @unlink($path . '.icc');

                $iptc = $this->_get_iptc_data($path);
                $this->has_iptc = !empty($iptc);

                $exif = $this->_get_exif_data($path);
                $this->has_exif = !empty($exif);

                if (isset($iptc['2#005'])) {
                    if (is_array($iptc['2#005'])) {
                        $iptc['2#005'] = $iptc['2#005'][0];
                    }
                    $this->title = $this->_force_utf($iptc['2#005']);
                } elseif (isset($iptc['2#105'])) {
                    if (is_array($iptc['2#105'])) {
                        $iptc['2#105'] = $iptc['2#105'][0];
                    }
                    $this->title = $this->_force_utf($iptc['2#105']);
                }

                if (isset($iptc['2#120'])) {
                    if (is_array($iptc['2#120'])) {
                        $iptc['2#120'] = $iptc['2#120'][0];
                    }
                    $this->caption = $this->_force_utf($iptc['2#120']);
                }

                if (isset($iptc['2#025']) && is_array($iptc['2#025'])) {
                    $words = [];

                    if (count($iptc['2#025']) == 1) {
                        $words = [$iptc['2#025'][0]];
                    } else {
                        $words = $iptc['2#025'];
                    }

                    $_POST['tags'] = rtrim((string) $_POST['tags'], ',') . ',' . join(',', $words);
                }

                $captured_on = $this->parse_captured($iptc, $exif);

                if (!is_null($captured_on) && $captured_on > 0) {
                    $this->captured_on = $captured_on;
                }

                $longest = max($this->width, $this->height);
                $midsize = preg_replace('/\.' . $pathinfo['extension'] . '$/', '.1600.' . $pathinfo['extension'], (string) $path);

                if (file_exists($midsize)) {
                    unlink($midsize);
                }

                $orientation = $exif['IFD0']['Orientation'] ?? false;

                if (in_array($orientation, [3, 6, 8], true)) {
                    include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'DarkroomUtils.php');

                    $s = new Setting();
                    $s->where('name', 'image_processing_library')->get();

                    $d = DarkroomUtils::init($s->value);

                    switch ($orientation) {
                        case 3:
                            $degrees = 180;
                            break;
                        case 6:
                            $degrees = 90;
                            break;
                        case 8:
                            $degrees = 270;
                            break;
                    }

                    $d->rotate($path, $degrees);

                    if ($orientation !== 3) {
                        // swap values
                        $width = $this->width;
                        [$this->width, $this->height] = [$this->height, $width];
                    }
                }

                if ($longest > 1600) {
                    include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'DarkroomUtils.php');

                    $s = new Setting();
                    $s->where('name', 'image_processing_library')->get();

                    $d = DarkroomUtils::init($s->value);

                    $quality = $d->read($path)->getQuality();
                    $quality = empty($quality) ? 100 : $quality;

                    $d->read($path, $this->width, $this->height)
                      ->resize(1600)
                      ->quality($quality)
                      ->render($midsize);

                    $external = Shutter::store_original($midsize, $this->path . '/' . basename((string) $midsize));

                    if ($external) {
                        $this->storage_url_midsize = $external;
                        unlink($midsize);
                    }
                }
            }
            $this->filesize = filesize($path);
        }

        if (is_numeric($this->width)) {
            $this->aspect_ratio = $this->width / $this->height;
        }
    }

    public function _set_paths()
    {
        $this->path = substr($this->internal_id, 0, 2) . DIRECTORY_SEPARATOR . substr($this->internal_id, 2, 2);

        $padded_id = str_pad($this->id, 6, '0', STR_PAD_LEFT);
        $this->cache_path = substr($padded_id, 0, 3) . DIRECTORY_SEPARATOR . substr($padded_id, 3);
    }

    // General prep. We use the get_rule on filename to ensure this is always run, as filename is always present
    public function _readify()
    {
        $this->_set_paths();
        $this->focal_point = is_string($this->focal_point) ? json_decode($this->focal_point) : ['x' => 50, 'y' => 50];
    }

    // Called by plugins
    public function create($options = [])
    {
        $defaults = ['tags' => []];

        $options = array_merge($defaults, $options);

        if ($this->save()) {
            if (!empty($options['tags'])) {
                $this->_format_tags(join(',', $options['tags']));
            }

            $this->_readify();
            $content = $this->to_array(['auth' => true]);
            Shutter::hook('content.create', $content);
            return $content['id'];
        }
        return false;
    }

    /**
     * Constructor: calls parent constructor
     */
    public function __construct($id = null)
    {
        $db_config = Shutter::get_db_configuration();

        $this->has_many = ['text' => ['other_field' => 'featured_image'], 'album', 'tag', 'category', 'covers' => ['class' => 'album', 'join_table' => $db_config['prefix'] . 'join_albums_covers', 'other_field' => 'cover', 'join_self_as' => 'cover', 'join_other_as' => 'album']];
        parent::__construct($id);
    }

    public function clear_cache()
    {
        $this->file_modified_on = time();
        $this->save();
        Shutter::clear_cache('images/' . $this->cache_path);
    }

    public function do_delete()
    {
        $a = new Album();
        $previews = $a->where_related('cover', 'id', $this->id)->get_iterated();
        foreach ($previews as $a) {
            $a->reset_covers();
        }

        $albums = $a->where_related('content', 'id', $this->id)->get_iterated();
        foreach ($albums as $a) {
            $a->update_counts();
        }

        $this->clear_cache();

        if (empty($this->storage_url)) {
            $original = $this->path_to_original();
            $info = pathinfo((string) $original);
            $mid = preg_replace('/\.' . $info['extension'] . '$/', '.1600.' . $info['extension'], (string) $original);
            unlink($original);
            if (file_exists($mid)) {
                unlink($mid);
            }
            if ($this->file_type > 0 && is_dir($original . '_previews')) {
                delete_files($original . '_previews', true, 1);
            }

            if (@rmdir(dirname((string) $original))) {
                @rmdir(dirname((string) $original, 2));
            }
        } else {
            Shutter::delete_original($this->storage_url);

            if (!empty($this->storage_url_midsize)) {
                Shutter::delete_original($this->storage_url_midsize);
            }
        }

        Shutter::hook('content.delete', $this->to_array(['auth' => true]));

        $s = new Slug();
        $this->db->query("DELETE FROM {$s->table} WHERE id = 'content.{$this->slug}'");

        $this->delete();
    }

    public function set_type()
    {
        $image_types = ['jpg', 'jpeg', 'png', 'gif'];
        $audio_types = ['mp3'];
        $info = pathinfo($this->filename);
        $ext = strtolower($info['extension']);

        return match (true) {
            in_array($ext, $image_types) => 0,
            in_array($ext, $audio_types) => 2,
            default => 1,
        };
    }

    public function path_to_original()
    {
        if (!in_array('path', $this->fields)) {
            $this->_set_paths();
        }

        if (!empty($this->storage_url)) {
            return $this->storage_url;
        }

        return FCPATH . 'storage' .
                DIRECTORY_SEPARATOR . 'originals' .
                DIRECTORY_SEPARATOR . $this->path .
                DIRECTORY_SEPARATOR . basename($this->filename);
    }

    public function parse_captured($iptc, $exif)
    {
        $captured_on = null;

        if (isset($exif['EXIF']['DateTimeOriginal'])) {
            $dig = $exif['EXIF']['DateTimeOriginal'];
        } elseif (isset($exif['EXIF']['DateTimeDigitized'])) {
            $dig = $exif['EXIF']['DateTimeDigitized'];
        }

        if (isset($dig) && preg_match('/\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/', (string) $dig)) {
            $bits = explode(' ', (string) $dig);
            $captured_on = strtotime(str_replace(':', '-', $bits[0]) . ' ' . $bits[1]);
        } elseif (!empty($iptc['2#055'][0]) && !empty($iptc['2#060'][0])) {
            $captured_on = strtotime($iptc['2#055'][0] . ' ' . $iptc['2#060'][0]);
        }
        return $captured_on;
    }

    public function compute_cache_size($w, $h, $square = false)
    {
        if ($this->file_type > 0) {
            if (empty($this->lg_preview)) {
                return false;
            } else {
                $array = explode(':', $this->lg_preview);
                $preview_file = array_shift($array);
                $preview_path = $this->path_to_original() . '_previews' . DIRECTORY_SEPARATOR . $preview_file;
                [$original_width, $original_height] = getimagesize($preview_path);
                $original_aspect = $original_width/$original_height;
            }
        } else {
            $original_width = $this->width;
            $original_height = $this->height;
            $original_aspect = $this->aspect_ratio;
        }

        if ($square) {
            $side = min($w, $h, $original_width, $original_height);
            return [$side, $side];
        } else {
            $target_aspect = $w/$h;
            if ($original_aspect >= $target_aspect) {
                if ($w > $original_width) {
                    return [$original_width, $original_height];
                } else {
                    return [$w, round(($w*$original_height)/$original_width)];
                }
            } else {
                if ($h > $original_height) {
                    return [$original_width, $original_height];
                } else {
                    return [round(($h*$original_width)/$original_height), $h];
                }
            }
        }
    }

    public function to_array_custom($filename)
    {
        $path = FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . $filename;

        // Fake out compute_cache_size et al to think this is a real image
        [$this->width, $this->height] = getimagesize($path);
        $this->aspect_ratio = round($this->width/$this->height, 3);
        $this->file_modified_on = filemtime($path);

        $info = pathinfo($path);
        $koken_url_info = $this->config->item('koken_url_info');
        $prefix = $info['filename'];
        $cache_base = $koken_url_info->base . (KOKEN_REWRITE ? 'storage/cache/images' : 'i.php?') . '/custom/' . $prefix . '-' . $info['extension'] . '/';
        $relative_cache_base = str_replace($koken_url_info->base, $koken_url_info->relative_base, $cache_base);

        $data = ['__koken__' => 'content', 'custom' => true, 'filename' => $filename, 'filesize' => filesize($path), 'width' => $this->width, 'height' => $this->height, 'aspect_ratio' => $this->aspect_ratio, 'cache_path' => ['prefix' => $cache_base, 'relative_prefix' => $relative_cache_base, 'extension' => $this->file_modified_on . '.' . $info['extension']], 'presets' => []];

        include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'DarkroomUtils.php');

        foreach (DarkroomUtils::$presets as $name => $opts) {
            $dims = $this->compute_cache_size($opts['width'], $opts['height']);
            if ($dims) {
                [$w, $h] = $dims;
                $data['presets'][$name] = ['url' => $cache_base . "$name.{$this->file_modified_on}.{$info['extension']}", 'hidpi_url' => $cache_base . "$name.2x.{$this->file_modified_on}.{$info['extension']}", 'width' => (int) $w, 'height' => (int) $h];

                [$cx, $cy] = $this->compute_cache_size($opts['width'], $opts['height'], true);

                $data['presets'][$name]['cropped'] = ['url' => $cache_base . "$name.crop.{$this->file_modified_on}.{$info['extension']}", 'hidpi_url' => $cache_base . "$name.crop.2x.{$this->file_modified_on}.{$info['extension']}", 'width' => (int) $cx, 'height' => (int) $cy];
            }
        }

        return $data;
    }

    public function to_array($options = [])
    {
        $options['auth'] ??= false;
        $options['in_album'] ??= false;

        $exclude = ['storage_url', 'storage_url_midsize', 'deleted', 'featured_order', 'favorite_order', 'old_slug', 'has_exif', 'has_iptc', 'tags_old'];
        $bools = ['featured', 'favorite'];
        $dates = ['uploaded_on', 'modified_on', 'captured_on', 'featured_on', 'file_modified_on', 'published_on'];
        $strings = ['title', 'caption'];
        [$data, $fields] = $this->prepare_for_output($options, $exclude, $bools, $dates, $strings);

        $data = Shutter::filter('api.content.before', [$data, $this, $options]);

        if (!$data['featured']) {
            unset($data['featured_on']);
        }

        if (!$data['favorite']) {
            unset($data['favorited_on']);
        }

        $koken_url_info = $this->config->item('koken_url_info');

        if ($this->file_type != 0 && !empty($this->lg_preview)) {
            $array = explode(':', $this->lg_preview);
            $preview_file = array_shift($array);
        }

        if ($options['auth'] || $data['file_type'] != 0 || (int) $data['max_download'] === 1) {
            $pathinfo = pathinfo($this->filename);
            $path = 'storage/originals/' . str_replace(DIRECTORY_SEPARATOR, '/', $this->path) . '/' . $pathinfo['basename'];
            $url = $koken_url_info->base . $path;

            if (preg_match('/^https?:/', $this->filename)) {
                $data['original'] = [];
            } elseif (!empty($this->storage_url)) {
                $data['original'] = ['url' => $this->storage_url, 'width' => (int) $this->width, 'height' => (int) $this->height];

                if (!empty($this->storage_url_midsize)) {
                    $data['original']['midsize'] = $this->storage_url_midsize;
                }
            } else {
                $data['original'] = ['url' => $url, 'relative_url' => '/' . $path, 'width' => (int) $this->width, 'height' => (int) $this->height];
            }

            if (isset($preview_file)) {
                $path .= '_previews/' . $preview_file;
                $url = $koken_url_info->base . $path;
                [$pw, $ph] = getimagesize(FCPATH . $path);

                $data['original']['preview'] = ['url' => $url, 'relative_url' => '/' . $path, 'width' => $pw, 'height' => $ph];
            }
        }

        if (!$options['auth'] && $data['visibility'] == 0) {
            unset($data['internal_id']);
        }

        if (!preg_match('~https?://~', $this->filename)) {
            $info = pathinfo($this->filename);

            $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png', 'flv' => 'video/x-flv', 'f4v' => 'video/f4v', 'swf' => 'application/x-shockwave-flash', 'mov' => 'video/mp4', 'mp4' => 'video/mp4', 'm4v' => 'video/x-m4v', '3gp' => 'video/3gpp', '3g2' => 'video/3gpp2', 'mp3' => 'audio/mpeg'];

            if (array_key_exists(strtolower($info['extension']), $mimes)) {
                $data['mime_type'] = $mimes[strtolower($info['extension'])];
            } elseif (function_exists('mime_content_type')) {
                $data['mime_type'] = mime_content_type($this->path_to_original());
            } else {
                $data['mime_type'] = '';
            }
        } else {
            $data['mime_type'] = '';
        }

        $data['__koken__'] = 'content';

        if (!isset($options['include_presets']) || $options['include_presets']) {
            include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'DarkroomUtils.php');

            if ($this->file_type > 0 && empty($this->lg_preview)) {
                $prefix = $koken_url_info->base . 'admin/images/no-thumb,';
                $data['cache_path'] = ['prefix' => $prefix, 'extension' => 'png'];

                $data['presets'] = [];
                foreach (DarkroomUtils::$presets as $name => $opts) {
                    $h = ($opts['width'] * 2) / 3;
                    $data['presets'][$name] = ['url' => $koken_url_info->base . "admin/images/no-thumb,$name.png", 'width' => $opts['width'], 'height' => round($h)];

                    $data['presets'][$name]['cropped'] = ['url' => $koken_url_info->base . "admin/images/no-thumb,$name.crop.png", 'width' => $opts['width'], 'height' => $opts['width']];
                }
            } elseif ($this->file_type > 0 || !preg_match('~^https?://~', $this->filename)) {
                if (isset($info['extension'])) {
                    $prefix = preg_replace("/\.{$info['extension']}$/", '', basename($this->filename));
                } else {
                    $prefix = basename($this->filename);
                }

                if (isset($preview_file)) {
                    $info = pathinfo((string) $preview_file);
                }

                $cache_base = $koken_url_info->base . (KOKEN_REWRITE ? 'storage/cache/images' : 'i.php?') . '/' . str_replace('\\', '/', $this->cache_path) . '/' . $prefix . ',';
                $relative_cache_base = str_replace($koken_url_info->base, $koken_url_info->relative_base, $cache_base);

                $data['cache_path'] = ['prefix' => $cache_base, 'relative_prefix' => $relative_cache_base];

                $data['cache_path']['extension'] = $data['file_modified_on']['timestamp'] . '.' . $info['extension'];
                $data['presets'] = [];
                foreach (DarkroomUtils::$presets as $name => $opts) {
                    $dims = $this->compute_cache_size($opts['width'], $opts['height']);
                    if ($dims) {
                        [$w, $h] = $dims;
                        $data['presets'][$name] = ['url' => $cache_base . "$name.{$data['file_modified_on']['timestamp']}.{$info['extension']}", 'hidpi_url' => $cache_base . "$name.2x.{$data['file_modified_on']['timestamp']}.{$info['extension']}", 'width' => (int) $w, 'height' => (int) $h];

                        [$cx, $cy] = $this->compute_cache_size($opts['width'], $opts['height'], true);

                        $data['presets'][$name]['cropped'] = ['url' => $cache_base . "$name.crop.{$data['file_modified_on']['timestamp']}.{$info['extension']}", 'hidpi_url' => $cache_base . "$name.crop.2x.{$data['file_modified_on']['timestamp']}.{$info['extension']}", 'width' => (int) $cx, 'height' => (int) $cy];
                    }
                }
            }
        }

        if ($data['file_type'] == 0) {
            unset($data['duration']);
        }

        if (array_key_exists('duration', $data)) {
            $r = $data['duration'];
            $data['duration'] = [];
            $data['duration']['raw'] = $r;

            $m = floor($r/60);
            $s = str_pad(floor($r%60), 2, '0', STR_PAD_LEFT);

            if ($m > 60) {
                $h = floor($m/60);
                $m = str_pad(floor($m%60), 2, '0', STR_PAD_LEFT);
                $m = "$h:$m";
            }

            $data['duration']['clean'] = "$m:$s";
        }

        $data['iptc'] = $data['iptc_fields'] = $data['exif'] = $data['exif_fields'] = [];

        if ($this->has_iptc) {
            [$data['iptc'], $data['iptc_fields']] = $this->iptc_to_human();
        }

        $data['geolocation'] = false;

        if ($this->has_exif) {
            if (!isset($options['exif'])) {
                $options['exif'] = 'all';
            }

            $exif = $this->_get_exif_data();

            if (isset($exif['GPS']['GPSLatitude'])) {
                include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'gps.php');
                $gps = new GPS($exif['GPS']);
                $data['geolocation'] = ['latitude' => rtrim(sprintf('%.12f', $gps->latitude()), '0'), 'longitude' => rtrim(sprintf('%.12f', $gps->longitude()), '0')];
            }

            [$data['exif'], $data['exif_fields']] = $this->exif_to_human($data['exif'], $options['exif']);
        }

        if (array_key_exists('file_type', $data)) {
            switch ($data['file_type']) {
                // TODO: Make this array and include mime type? Ehhh?
                case 0:
                    $data['file_type'] = 'image';
                    break;
                case 1:
                    $data['file_type'] = 'video';
                    break;
                case 2:
                    $data['file_type'] = 'audio';
                    break;
            }
        }

        if (array_key_exists('visibility', $data)) {
            $raw = match ($data['visibility']) {
                1 => 'unlisted',
                2 => 'private',
                default => 'public',
            };

            $data['visibility'] = ['raw' => $raw, 'clean' => ucwords($raw)];

            $data['public'] = $raw === 'public';
        }

        if (array_key_exists('max_download', $data)) {
            switch ($data['max_download']) {
                case 0:
                    $data['max_download'] = 'none';
                    $clean = 'None';
                    break;
                case 1:
                    $data['max_download'] = 'original';
                    $clean = 'Original';
                    break;
                case 2:
                    $data['max_download'] = 'huge';
                    $clean = 'Huge (2048)';
                    break;
                case 3:
                    $data['max_download'] = 'xlarge';
                    $clean = 'X-Large (1600)';
                    break;
                case 4:
                    $data['max_download'] = 'large';
                    $clean = 'Large (1024)';
                    break;
                case 5:
                    $data['max_download'] = 'medium_large';
                    $clean = 'Medium-Large (800)';
                    break;
                case 6:
                    $data['max_download'] = 'medium';
                    $clean = 'Medium (480)';
                    break;
            }
            $data['max_download'] = ['raw' => $data['max_download'], 'clean' => $clean];
        }

        if (array_key_exists('license', $data) && !is_null($data['license'])) {
            if ($data['license'] == 'all') {
                $clean = '© All rights reserved';
            } else {
                // Data is stored as commercial,modifications ... (y|n),(y,s,n)
                // Example: NonCommercial ShareAlike == n,s
                [$commercial, $mods] = explode(',', (string) $data['license']);

                $license_url = 'http://creativecommons.org/licenses/by';

                if ($commercial == 'y') {
                    $clean = 'Commercial';
                } else {
                    $license_url .= '-nc';
                    $clean = 'NonCommercial';
                }

                switch ($mods) {
                    case 'y':
                        // Nothing to do here, standard license
                        break;
                    case 's':
                        $clean .= '-ShareAlike';
                        $license_url .= '-sa';
                        break;
                    case 'n':
                        $clean .= '-NoDerivs';
                        $license_url .= '-nd';
                        break;
                }

                $license_url .= '/3.0/deed.en_US';
            }
            $data['license'] = ['raw' => $data['license'], 'clean' => $clean];

            if (isset($license_url)) {
                $data['license']['url'] = $license_url;
            }
        }

        $data['tags'] = $this->_get_tags_for_output($options);

        $data['categories'] = ['count' => is_null($this->category_count) ? $this->categories->count() : (int) $this->category_count, 'url' => $koken_url_info->base . 'api.php?/content/' . $data['id'] . '/categories'];

        $data['albums'] = ['count' => is_null($this->album_count) ? $this->albums->count() : (int) $this->album_count, 'url' => $koken_url_info->base . 'api.php?/content/' . $data['id'] . '/albums'];

        if (isset($options['order_by']) && in_array($options['order_by'], ['uploaded_on', 'modified_on', 'captured_on'])) {
            $data['date'] =& $data[ $options['order_by'] ];
        } else {
            $data['date'] =& $data['published_on'];
        }

        if ($data['visibility'] === 'private') {
            $data['url'] = false;
        } else {
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
            $data['url'] = $this->url(['date' => $data['published_on'], 'album' => $options['in_album'], 'tag' => $options['tags'] ?? (isset($options['context']) && str_starts_with((string) $options['context'], 'tag-') ? str_replace('tag-', '', $options['context']) : false), 'category' => $cat, 'favorite' => isset($options['favorite']) || (isset($options['context']) && $options['context'] === 'favorites') ? true : false, 'feature' => isset($options['featured']) || (isset($options['context']) && $options['context'] === 'features') ? true : false]);

            if ($data['url']) {
                [$data['__koken_url'], $data['url']] = $data['url'];
                $data['canonical_url'] = $data['url'];
            }
        }

        if (!$options['auth'] && $data['visibility'] === 'unlisted') {
            unset($data['url']);
        }

        if (empty($data['source'])) {
            $data['source'] = false;
        } else {
            $data['source'] = ['title' => $data['source'], 'url' => $data['source_url']];
        }

        unset($data['source_url']);

        $final = Shutter::filter('api.content', [$data, $this, $options]);

        if (!isset($final['html']) || empty($final['html'])) {
            $final['html'] = false;
        }

        return $final;
    }

    public function greatest_common_denominator($a, $b)
    {
        if ($b === 0) {
            return $a;
        }
        return $this->greatest_common_denominator($b, ($a % $b));
    }

    public function simplify_fraction($a, $b)
    {
        while (($gcd = $this->greatest_common_denominator($a, $b)) > 1) {
            $a /= $gcd;
            $b /= $gcd;
        }
        return "$a/$b";
    }

    public function iptc_to_human()
    {
        $mappings = ['byline' 		=> ['label' => 'Byline', 'index' => '080'], 'byline_title' 	=> ['label' => 'Byline title', 'index' => '085'], 'caption' 		=> ['label' => 'Caption', 'index' => '120'], 'category' 		=> ['label' => 'Category', 'index' => '050'], 'city' 			=> ['label' => 'City', 'index' => '090'], 'country' 		=> ['label' => 'Country', 'index' => '101'], 'copyright' 	=> ['label' => 'Copyright', 'index' => '116'], 'contact' 		=> ['label' => 'Contact', 'index' => '118'], 'credit' 		=> ['label' => 'Credit', 'index' => '110'], 'headline' 		=> ['label' => 'Headline', 'index' => '105'], 'keywords' 		=> ['label' => 'Keywords', 'index' => '025'], 'source' 		=> ['label' => 'Source', 'index' => '115'], 'state' 		=> ['label' => 'State', 'index' => '095'], 'title' 		=> ['label' => 'Title', 'index' => '005']];

        $iptc = $this->_get_iptc_data();

        if (!$iptc || empty($iptc)) {
            return [[], []];
        } else {
            $final = $keys = [];

            foreach ($mappings as $name => $options) {
                $index = "2#{$options['index']}";
                if (isset($iptc[$index])) {
                    $value = $iptc[$index];
                    if (is_array($value)) {
                        $value = $value[0];
                    }
                    $value = preg_replace('/<script.*>.*<\/script>/', '', (string) $value);
                    $keys[] = $name;
                    $final[] = ['label' => $options['label'], 'value' => $this->_force_utf($value), 'key' => $name];
                }
            }

            natcasesort($keys);
            return [$final, array_values($keys)];
        }
    }

    public function exif_to_human($exif, $include = 'all')
    {
        $mappings = ['make' => ['label' => 'Camera make', 'field' => "IFD0.Make"], 'model' => ['label' => 'Camera', 'field' => "IFD0.Model", 'core' => true], 'lens_make' => ['label' => 'Lens make', 'field' => "EXIF.LensMake"], 'image_description' => ['label' => 'Description', 'field' => "IFD0.ImageDescription"], 'aperture' => ['label' => 'Aperture', 'field' => "EXIF.FNumber", 'divide' => true, 'pre' => 'f/', 'core' => true], 'aperture_max' => ['label' => 'Max aperture', 'field' => "EXIF.MaxApertureValue", 'divide' => true, 'pre' => 'f/'], 'exposure' => ['label' => 'Exposure', 'field' => "EXIF.ExposureTime", 'divide' => true, 'post' => ' sec', 'core' => true], 'exposure_bias' => ['label' => 'Exposure bias', 'field' => "EXIF.ExposureBiasValue", 'divide' => true, 'post' => ' EV'], 'exposure_mode' => ['label' => 'Exposure mode', 'field' => "EXIF.ExposureMode", 'values' => [0 => 'Auto', 1 => 'Manual', 2 => 'Auto bracket']], 'exposure_program' => ['label' => 'Exposure program', 'field' => "EXIF.ExposureProgram", 'values' => [0 => 'Not Defined', 1 => 'Manual', 2 => 'Program AE', 3 => 'Aperture-priority AE', 4 => 'Shutter speed priority AE', 5 => 'Creative (Slow speed)', 6 => 'Action (High speed)', 7 => 'Portrait', 8 => 'Landscape', 9 => 'Bulb']], 'date_time_original' => ['label' => "Date Time Original", 'field' => 'EXIF.DateTimeOriginal'], 'flash' => ['label' => 'Flash', 'field' => 'EXIF.Flash', 'boolean' => ['test' => [0, 16, 24, 32], 'result' => false], 'values' => [0 => 'No Flash', 1 => 'Flash', 5 => 'Flash, strobe return light not detected', 7 => 'Flash, strob return light detected', 9 => 'Compulsory Flash', 13 => 'Compulsory Flash, Return light not detected', 16 => 'No Flash', 24 => 'No Flash', 25 => 'Flash, Auto-Mode', 29 => 'Flash, Auto-Mode, Return light not detected', 31 => 'Flash, Auto-Mode, Return light detected', 32 => 'No Flash', 65 => 'Red Eye', 69 => 'Red Eye, Return light not detected', 71 => 'Red Eye, Return light detected', 73 => 'Red Eye, Compulsory Flash', 77 => 'Red Eye, Compulsory Flash, Return light not detected', 79 => 'Red Eye, Compulsory Flash, Return light detected', 89 => 'Red Eye, Auto-Mode', 93 => 'Red Eye, Auto-Mode, Return light not detected', 95 => 'Red Eye, Auto-Mode, Return light detected']], 'focal_length' => ['label' => 'Focal length', 'field' => "EXIF.FocalLength", 'divide' => true, 'post' => 'mm', 'core' => true], 'iso_speed_ratings' => ['label' => 'ISO', 'field' => 'EXIF.ISOSpeedRatings', 'core' => true, 'pre' => 'ISO '], 'metering_mode' => ['label' => 'Metering mode', 'field' => 'EXIF.MeteringMode', 'values' => [0 => 'Unknown', 1 => 'Average', 2 => 'Center Weighted Average', 3 => 'Spot', 4 => 'Multi-Spot', 5 => 'Multi-Segment', 6 => 'Partial', 255 => 'Other']], 'white_balance' => ['label' => 'White balance', 'field' => 'EXIF.WhiteBalance', 'values' => [0 => 'Auto', 1 => 'Sunny', 2 => 'Cloudy', 3 => 'Tungsten', 4 => 'Fluorescent', 5 => 'Flash', 6 => 'Custom', 129 => 'Manual']], 'light_source' => ['label' => 'Light source', 'field' => 'EXIF.LightSource', 'values' => [0 => 'Unknown', 1 => 'Daylight', 2 => 'Fluorescent', 3 => 'Tungsten (Incandescent)', 4 => 'Flash', 9 => 'Fine Weather', 10 => 'Cloudy', 11 => 'Shade', 12 => 'Daylight Fluorescent', 13 => 'Day White Fluorescent', 14 => 'Cool White Fluorescent', 15 => 'White Fluorescent', 16 => 'Warm White Fluorescent', 17 => 'Standard Light A', 18 => 'Standard Light B', 20 => 'D55', 21 => 'D65', 22 => 'D75', 23 => 'D50', 24 => 'ISO Studio Tungsten', 255 => 'Other']], 'scene_capture_type' => ['label' => 'Scene capture type', 'field' => 'EXIF.SceneCaptureType', 'values' => [0 => 'Standard', 1 => 'Landscape', 2 => 'Portrait', 3 => 'Night']]];

        $exif = $this->_get_exif_data();

        if (!$exif || empty($exif)) {
            return [[], []];
        } else {
            if (strpos((string) $include, ',')) {
                $include = explode(',', (string) $include);
            }
            $final = $keys = [];
            $defaults = ['divide' => false, 'pre' => '', 'post' => '', 'values' => false, 'boolean' => false, 'core' => false];
            foreach ($mappings as $property => $options) {
                $options = array_merge($defaults, $options);
                if (is_array($include) && !in_array($property, $include)) {
                    continue;
                } elseif ($include == 'core' && !$options['core']) {
                    continue;
                }

                $bits = explode('.', $options['field']);
                if (isset($exif[$bits[0]][$bits[1]])) {
                    $value = $exif[$bits[0]][$bits[1]];
                    if (is_array($value)) {
                        $value = $value[0];
                    }

                    $value = preg_replace('/<script.*>.*<\/script>/', '', (string) $value);

                    if ($options['divide']) {
                        [$n, $d] = explode('/', (string) $value);
                        if ($d < 1) {
                            $result = $value = 0;
                        } else {
                            $result = round($n / $d, 6);
                            $value = $this->simplify_fraction($n, $d);
                        }

                        if ($property !== 'exposure' || $result >= 1) {
                            $clean = $result;
                        } else {
                            $clean = $value;
                        }
                    } elseif ($options['values'] && isset($options['values'][(int) $value])) {
                        $clean = $options['values'][(int) $value];
                    } else {
                        $value = trim((string) $value);
                        if (!empty($options['pre']) || !empty($options['post'])) {
                            $clean = $value;
                        }
                    }
                    if ($options['boolean']) {
                        $result = isset($options['boolean']['true']) ? true : false;
                        $test = in_array($value, $options['boolean']['test']);
                        if ($test) {
                            $bool = $options['boolean']['result'];
                        } else {
                            $bool = !$options['boolean']['result'];
                        }
                    }
                    $arr = [];
                    $arr['key'] = $property;
                    $arr['label'] = $options['label'];
                    $arr['raw'] = $this->_force_utf($value);
                    if (isset($clean)) {
                        $arr['computed'] = $this->_force_utf($clean);
                        $arr['clean'] = $options['pre'] . $clean . $options['post'];
                        unset($clean);
                    }
                    if (isset($bool)) {
                        $arr['bool'] = $bool;
                        unset($bool);
                    }

                    $final[] = $arr;
                    $keys[] = $property;
                }
            }

            // Lens
            $lens = false;

            // Best Lens info is in this tag
            if (isset($exif['EXIF']['UndefinedTag:0xA434'])) {
                $lens = trim((string) $exif['EXIF']['UndefinedTag:0xA434']);
            }
            // If the above doesn't work, this is a fallback
            elseif (isset($exif['EXIF']['UndefinedTag:0xA432'])) {
                $val = $exif['EXIF']['UndefinedTag:0xA432'];
                $array = explode('/', (string) $val[0]);
                $short = array_shift($array);
                $array1 = explode('/', (string) $val[1]);
                $long = array_shift($array1);
                $lens = $short;
                if ($short != $long) {
                    $lens .= '-' . $long;
                }
                $lens .= ' mm';
            }

            if ($lens) {
                $final[] = ['label' => 'Lens', 'raw' => $this->_force_utf($lens), 'key' => 'lens'];
                $keys[] = 'lens';
            }

            natcasesort($keys);
            return [$final, array_values($keys)];
        }
    }

    public function listing($params, $id = false)
    {
        $sort = $this->_get_site_order('content');

        $options = ['order_by' => $sort['by'], 'order_direction' => $sort['direction'], 'search' => false, 'search_filter' => false, 'tags' => false, 'tags_not' => false, 'page' => 1, 'match_all_tags' => false, 'limit' => 100, 'include_presets' => true, 'featured' => null, 'types' => false, 'auth' => false, 'favorites' => null, 'before' => false, 'after' => false, 'after_column' => 'uploaded_on', 'before_column' => 'uploaded_on', 'category' => false, 'category_not' => false, 'year' => false, 'year_not' => false, 'month' => false, 'month_not' => false, 'day' => false, 'day_not' => false, 'in_album' => false, 'reduce' => false, 'is_cover' => true, 'independent' => false];

        $options = array_merge($options, $params);

        if (isset($params['order_by']) && !isset($params['order_direction'])) {
            $options['order_direction'] = in_array($params['order_by'], ['title', 'filename']) ? 'ASC' : 'DESC';
        }

        Shutter::hook('content.listing', [$this, $options]);

        if ($options['featured'] == 1 && !isset($params['order_by'])) {
            $options['order_by'] = 'featured_on';
        } elseif ($options['favorites'] == 1 && !isset($params['order_by'])) {
            $options['order_by'] = 'favorited_on';
        }

        if ($options['auth']) {
            if (isset($options['visibility']) && $options['visibility'] !== 'album') {
                $values = ['public', 'unlisted', 'private'];
                if (in_array($options['visibility'], $values)) {
                    $options['visibility'] = array_search($options['visibility'], $values);
                } elseif ($options['visibility'] === 'any') {
                    $options['visibility'] = false;
                } else {
                    $options['visibility'] = 0;
                }
            } elseif (!isset($options['visibility']) || $options['visibility'] !== 'album') {
                $options['visibility'] = 0;
            }
        } elseif ($options['in_album']) {
            $options['visibility'] = 'album';
        } else {
            $options['visibility'] = 0;
        }

        if ($options['visibility'] > 0 && $options['order_by'] === 'published_on') {
            $options['order_by'] = 'captured_on';
        }

        if ($options['order_by'] == 'dimension') {
            $options['order_by'] = 'width * height';
        }
        if (is_numeric($options['limit']) && $options['limit'] > 0) {
            $options['limit'] = min($options['limit'], 500);
        } else {
            $options['limit'] = 100;
        }

        if ($options['independent']) {
            $this->where_related('album', 'id', null);
        }

        if ($options['types']) {
            $types = explode(',', str_replace(' ', '', $options['types']));
            $this->group_start();
            foreach ($types as $t) {
                switch ($t) {
                    case 'photo':
                        $this->or_where('file_type', 0);
                        break;

                    case 'video':
                        $this->or_where('file_type', 1);
                        break;

                    case 'audio':
                        $this->or_where('file_type', 2);
                        break;
                }
            }
            $this->group_end();
        }

        if ($options['search'] && $options['search_filter'] === 'tags') {
            $options['tags'] = $options['search'];
            $options['search'] = false;
        }

        if ($options['search']) {
            $term = urldecode((string) $options['search']);

            if ($options['search_filter']) {
                if ($options['search_filter'] === 'category') {
                    $cat = new Category();
                    $cat->where('title', $term)->get();
                    if ($cat->exists()) {
                        $this->where_related('category', 'id', $cat->id);
                    } else {
                        $this->where_related('category', 'id', 0);
                    }
                } else {
                    $this->group_start();
                    $this->like($options['search_filter'], $term, 'both');
                    $this->group_end();
                }
            } else {
                $this->group_start();
                $this->like('title', $term, 'both');
                $this->or_like('caption', $term, 'both');

                $t = new Tag();
                $t->where('name', $term)->get();

                if ($t->exists()) {
                    $this->or_where_related('tag', 'id', $t->id);
                }

                $this->group_end();
            }
        } elseif ($options['tags'] || $options['tags_not']) {
            $this->_do_tag_filtering($options);
        }

        if (!is_null($options['featured'])) {
            $this->where('featured', $options['featured']);
        }
        if (!is_null($options['favorites'])) {
            $this->where('favorite', $options['favorites']);
        }
        if ($options['category']) {
            $this->where_related('category', 'id', $options['category']);
        } elseif ($options['category_not']) {
            $cat = new Content();
            $cat->select('id')->where_related('category', 'id', $options['category_not'])->get_iterated();
            $cids = [];
            foreach ($cat as $c) {
                $cids[] = $c->id;
            }
            $this->where_not_in('id', $cids);
        }
        if ($options['after']) {
            $this->where($options['after_column'] . ' >=', $options['after']);
        }
        if ($options['before']) {
            $this->where($options['before_column'] . ' <=', $options['before']);
        }
        if ($options['visibility'] === 'album') {
            $this->where('visibility <', $options['in_album']->visibility + 1);
        } elseif ($options['visibility'] !== false) {
            $this->where('visibility', $options['visibility']);
        }
        if ($id) {
            $sql_order = "ORDER BY FIELD(id,$id)";
            $id = explode(',', (string) $id);
            $this->where_in('id', $id);
        }

        if ($options['order_by'] === 'captured_on' || $options['order_by'] === 'uploaded_on' || $options['order_by'] === 'modified_on' || $options['order_by'] === 'published_on') {
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

        // Do this before date filters are applied
        $bounds = $this->get_clone()
                    ->select('COUNT(DISTINCT ' . $this->table . '.id) as count, MONTH(FROM_UNIXTIME(' . $bounds_order . $shift . ')) as month, YEAR(FROM_UNIXTIME(' . $bounds_order . $shift . ')) as year')
                    ->group_by('id,month,year')
                    ->order_by('year')
                    ->get_iterated();

        $dates = [];
        foreach ($bounds as $b) {
            if (!is_numeric($b->year)) {
                continue;
            }
            if (!isset($dates[$b->year])) {
                $dates[$b->year] = [];
            }

            $dates[$b->year][$b->month] = (int) $b->count;
        }

        if (in_array($options['order_by'], ['captured_on', 'uploaded_on', 'modified_on'])) {
            $date_col = $options['order_by'];
        } else {
            $date_col = 'published_on';
        }

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

            if ($options['reduce']) {
                $a = new Album();
                $a->select('id')
                    ->where('deleted', 0)
                    ->where('visibility', 0)
                    ->where('YEAR(FROM_UNIXTIME(' . $a->table . '.published_on' . $shift . '))', $options['year'])
                    ->where('MONTH(FROM_UNIXTIME(' . $a->table . '.published_on' . $shift . '))', $options['month'])
                    ->where('DAY(FROM_UNIXTIME(' . $a->table . '.published_on' . $shift . '))', $options['day'])
                    ->include_related('content', 'id')
                    ->get_iterated();

                $ids = [];
                foreach ($a as $album) {
                    if ($album->content_id) {
                        $ids[] = $album->content_id;
                    }
                }

                $e = new Text();
                $e->select('featured_image_id')
                    ->where('page_type', 0)
                    ->where('published', 1)
                    ->where('featured_image_id >', 0)
                    ->where('YEAR(FROM_UNIXTIME(' . $e->table . '.published_on' . $shift . '))', $options['year'])
                    ->where('MONTH(FROM_UNIXTIME(' . $e->table . '.published_on' . $shift . '))', $options['month'])
                    ->where('DAY(FROM_UNIXTIME(' . $e->table . '.published_on' . $shift . '))', $options['day'])
                    ->get_iterated();

                foreach ($e as $essay) {
                    if ($essay->featured_image_id) {
                        $ids[] = $essay->featured_image_id;
                    }
                }

                if (!empty($ids)) {
                    $this->where_not_in('id', $ids);
                }
            }
        }

        $vid_count = $this->get_clone()->where('file_type', 1)->count();
        $aud_count = $this->get_clone()->where('file_type', 2)->count();

        $final = $this->paginate($options);
        $final['dates'] = $dates;

        $this->include_related_count('albums', null, ['visibility' => 0]);
        $this->include_related_count('categories');

        if ($id && !isset($params['order_by'])) {
            $q = explode('LIMIT', $this->get_sql());
            $query = $q[0] . $sql_order . ' LIMIT ' . $q[1];
            $data = $this->query($query);
        } else {
            if ($options['order_by'] === 'title') {
                $q = explode('LIMIT', $this->get_sql());
                $query = preg_replace('/SELECT\s(`[^`]+`\.\*)/', "SELECT COALESCE(NULLIF(title, ''), filename) as order_title, $1", $q[0]);
                $query .= 'ORDER BY order_title ' . $options['order_direction'] . ' LIMIT ' . $q[1];
                $data = $this->query($query);
            } else {
                $data = $this->order_by($options['order_by'] . ' ' . $options['order_direction'] . ', id ' . $options['order_direction'])
                    ->get_iterated();
            }
        }

        if (!$options['limit']) {
            $final['per_page'] = $data->result_count();
            $final['total'] = $data->result_count();
        }

        $final['counts'] = ['videos' => $vid_count, 'audio' => $aud_count, 'images' => $final['total'] - $vid_count - $aud_count, 'total' => $final['total']];

        $final['content'] = [];

        $final['sort'] = $sort;

        $tag_map = $this->_eager_load_tags($data);

        foreach ($data as $content) {
            $tags = $tag_map['c' . $content->id] ?? [];
            $options['eager_tags'] = $tags;
            $final['content'][] = $content->to_array($options);
        }
        return $final;
    }
}

/* End of file content.php */
/* Location: ./application/models/content.php */
