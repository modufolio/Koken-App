<?php

class Settings extends Koken_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function test_email()
    {
        if (!$this->auth) {
            $this->error('403', 'Forbidden');
            return;
        }

        $email = $this->input->post('delivery_address');
        $sender = $this->input->post('sender');

        Shutter::email($email, $email, $email, '[' . $_SERVER['HTTP_HOST'] . '] Koken email test', 'Message received! Your Koken email configuration appears to be working fine.', $sender);

        exit;
    }

    public function index()
    {
        if (!$this->auth) {
            $this->error('403', 'Forbidden');
            return;
        }

        $image_processing = new Setting();
        $image_processing->where('name', 'image_processing_library')->get();
        include(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'DarkroomUtils.php');
        $libs = DarkroomUtils::libraries();

        $array = array_keys($libs);
        if ($image_processing->exists()) {
            if (!isset($libs[$image_processing->value])) {
                $top = array_shift($array);
                $lib = $libs[$top];
                $image_processing->value = $lib['key'];
                $image_processing->save();
            }
        } else {
            if (!defined('MAGICK_PATH_FINAL') || (MAGICK_PATH_FINAL === 'convert' || !isset($libs[MAGICK_PATH_FINAL]))) {
                $top = array_shift($array);
                $lib = $libs[$top];
            } else {
                $lib = $libs[MAGICK_PATH_FINAL];
            }

            $image_processing->name = 'image_processing_library';
            $image_processing->value = $lib['key'];
            $image_processing->save();
        }

        $last_check = new Setting();
        $last_check->where('name', 'last_migration');
        $last_check_count = $last_check->count();

        if ($last_check_count > 1) {
            $last_check->where('name', 'last_migration')->order_by('value ASC')->limit($last_check_count-1)->get();
            $last_check->delete_all();
        }

        $s = new Setting();
        $settings = $s->get_iterated();

        $data = array('image_processing_libraries' => array_values($libs));
        $bools = array('has_toured', 'site_hidpi', 'retain_image_metadata', 'image_use_defaults', 'use_default_labels_links', 'uploading_publish_on_captured_date');

        foreach ($settings as $setting) {
            // Don't allow dupes to screw things up
            if (isset($data[$setting->name])) {
                continue;
            }

            $value = $setting->value;
            if (in_array($setting->name, $bools)) {
                $value = $value == 'true';
            }

            if ($setting->name === 'last_upload') {
                $value = $value === 'false' ? false : (int) $value;
            }

            $data[ $setting->name ] = $value;
        }

        if (!isset($data['uploading_publish_on_captured_date'])) {
            $data['uploading_publish_on_captured_date'] = false;
        }

        if (!isset($data['uploading_default_album_visibility'])) {
            $data['uploading_default_album_visibility'] = 'public';
        }

        if (!isset($data['email_handler'])) {
            $data['email_handler'] = 'DDI_Email';
        }

        $data['email_handlers'] = Shutter::get_email_handlers();

        $disable_cache_file = FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'no-site-cache';
        $data[ 'enable_site_cache' ] = !file_exists($disable_cache_file);

        if ($this->method != 'get') {
            if ($this->auth_role !== 'god') {
                $this->error('403', 'Forbidden');
                return;
            }

            if (isset($_POST['signin_bg'])) {
                $c = new Content();
                $c->get_by_id($_POST['signin_bg']);

                if ($c->exists()) {
                    $_c = $c->to_array();
                    $large = array_pop($_c['presets']);
                    // TODO: Error checking for permissions reject
                    $f = $large['url'];
                    $to = FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'wallpaper' . DIRECTORY_SEPARATOR . 'signin.jpg';

                    if (extension_loaded('curl')) {
                        $cp = curl_init($f);
                        $fp = fopen($to, "w+");
                        if (!$fp) {
                            curl_close($cp);
                        } else {
                            curl_setopt($cp, CURLOPT_FILE, $fp);
                            curl_exec($cp);
                            curl_close($cp);
                            fclose($fp);
                        }
                    } elseif (ini_get('allow_url_fopen')) {
                        copy($f, $to);
                    }
                }
            } else {
                if (isset($_POST['enable_site_cache'])) {
                    if ($_POST['enable_site_cache'] === 'true') {
                        @unlink($disable_cache_file);
                    } else {
                        touch($disable_cache_file);
                        delete_files(dirname($disable_cache_file) . DIRECTORY_SEPARATOR . 'site', true, 1);
                    }
                    unset($_POST['enable_site_cache']);
                }

                // TODO: Make sure new path is not inside real_base
                // TODO: Ensure that real_base is not deleted under any circumstances
                if (isset($_POST['site_url']) && $_POST['site_url'] !== $data['site_url']) {
                    $_POST['site_url'] = strtolower(rtrim($_POST['site_url'], '/'));

                    if (empty($_POST['site_url'])) {
                        $_POST['site_url'] = '/';
                    }

                    if (isset($_SERVER['PHP_SELF']) && isset($_SERVER['SCRIPT_FILENAME'])) {
                        $php_self = str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['PHP_SELF']);
                        $doc_root = preg_replace('~' . $php_self . '$~i', '', $_SERVER['SCRIPT_FILENAME']);
                    } else {
                        $doc_root = $_SERVER['DOCUMENT_ROOT'];
                    }

                    $doc_root = realpath($doc_root);
                    $target = $doc_root . str_replace('/', DIRECTORY_SEPARATOR, $_POST['site_url']);
                    $php_include_base = rtrim(preg_replace('~^' . $doc_root . '~', '', FCPATH), DIRECTORY_SEPARATOR);
                    $real_base = $doc_root;

                    if (empty($php_include_base)) {
                        $real_base .= DIRECTORY_SEPARATOR;
                    } else {
                        $real_base .= $php_include_base;
                    }

                    @$target_dir = dir($target);
                    $real_base_dir = dir($real_base);

                    function compare_paths($one, $two)
                    {
                        return rtrim($one, DIRECTORY_SEPARATOR) === rtrim($two, DIRECTORY_SEPARATOR);
                    }

                    if ($target_dir && compare_paths($target_dir->path, $real_base_dir->path)) {
                        $_POST['site_url'] = 'default';
                        $htaccess = create_htaccess();
                        $root_htaccess = FCPATH . '.htaccess';
                        $current = file_get_contents($root_htaccess);
                        preg_match('/#MARK#.*/s', $htaccess, $match);
                        $htaccess = preg_replace('/#MARK#.*/s', str_replace('$', '\\$', $match[0]), $current);
                        file_put_contents($root_htaccess, $htaccess);
                    } else {
                        if ($target_dir) {
                            $reserved = array('admin', 'app', 'storage');
                            foreach ($reserved as $dir) {
                                $_dir = dir(rtrim($real_base_dir->path, '/') .  "/$dir");
                                if (compare_paths($target_dir->path, $_dir->path)) {
                                    $this->error('400', "This directory is reserved for Koken core files. Please choose another location.");
                                    return;
                                }
                            }
                        }

                        if (!make_child_dir($target)) {
                            $this->error('500', "Koken was not able to create the Site URL directory. Make sure the path provided is writable by the web server and try again.");
                            return;
                        }

                        $php_include_rel = str_replace(DIRECTORY_SEPARATOR, '/', $php_include_base);
                        $php_include_base = str_replace('\\', '\\\\', $php_include_base);
                        $doc_root_php = str_replace('\\', '\\\\', $doc_root);
                        $php = <<<OUT
<?php

	\$rewrite = false;
	\$real_base_folder = '$php_include_rel';
	require '{$doc_root_php}$php_include_base' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'site.php';
OUT;

                        $htaccess = create_htaccess($_POST['site_url']);

                        if ($this->check_for_rewrite()) {
                            $file = $target . DIRECTORY_SEPARATOR . '.htaccess';
                            $file_data = $htaccess;
                            $put_mode = FILE_APPEND;

                            if ($_POST['site_url'] !== 'default' && ("$doc_root" . DIRECTORY_SEPARATOR) !== FCPATH) {
                                $root_htaccess = FCPATH . '.htaccess';
                                if (file_exists($root_htaccess)) {
                                    $current = file_get_contents($root_htaccess);
                                    $redirect = create_htaccess($_POST['site_url'], true);
                                    preg_match('/#MARK#.*/s', $redirect, $match);
                                    $redirect = preg_replace('/#MARK#.*/s', str_replace('$', '\\$', $match[0]), $current);
                                    file_put_contents($root_htaccess, $redirect);
                                }
                            }
                        } else {
                            $file = $target . DIRECTORY_SEPARATOR . 'index.php';
                            $file_data = $php;
                            $put_mode = 0;
                        }

                        if (file_exists($file)) {
                            rename($file, "$file.bkup");
                        }

                        if (!file_put_contents($file, $file_data, $put_mode)) {
                            $this->error('500', "Koken was not able to create the necessary files in the Site URL directory. Make sure that path has sufficient permissions so that Koken may write the files.");
                            return;
                        }
                    }

                    if ($data['site_url'] !== 'default') {
                        $old = $doc_root . str_replace('/', DIRECTORY_SEPARATOR, $data['site_url']);

                        $old_dir = dir($old);

                        if (!compare_paths($old_dir->path, $real_base_dir->path)) {
                            if ($this->check_for_rewrite()) {
                                $old_file = $old .  DIRECTORY_SEPARATOR . '.htaccess';
                            } else {
                                $old_file = $old . DIRECTORY_SEPARATOR . 'index.php';
                            }

                            unlink($old_file);

                            $backup = $old_file . '.bkup';

                            if (file_exists($backup)) {
                                rename($backup, $old_file);
                            }

                            // This will only remove the dir if it is empty
                            @rmdir($old);
                        }
                    }
                }

                global $raw_input_data;

                if (isset($raw_input_data['url_data'])) {
                    $url_data = json_decode($raw_input_data['url_data'], true);
                    $u = new Url();
                    $u->order_by('id DESC')->get();
                    $existing_data = unserialize($u->data);

                    $transformed = [];

                    foreach ($url_data as $key => $udata) {
                        $transformed[] = array(
                            'type' => $key,
                            'data' => $udata
                        );
                    }

                    if ($existing_data !== $transformed) {
                        $n = new Url();
                        $n->data = serialize($transformed);
                        $n->save();
                    }

                    unset($_POST['url_data']);
                }

                $save = [];
                foreach ($_POST as $key => $val) {
                    if (isset($data[$key])) {
                        if (is_bool($data[$key]) && is_string($val) && ($val === 'true' || $val === 'false')) {
                            $save[$key] = $val;
                            $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                        }

                        if ($data[$key] !== $val) {
                            if ($key === 'retain_image_metadata' || (strpos($key, 'image_processing_library') === 0 && strpos($key, 'image_') === 0)) {
                                delete_files(FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images', true, 1);
                            }
                            if (!isset($save[$key])) {
                                $save[$key] = $val;
                            }
                        }
                    }
                }

                foreach ($save as $k => $v) {
                    $s = new Setting();
                    $s->where('name', $k)->get();

                    if ($s->exists()) {
                        $s->value = $v;
                        $s->save();
                    } elseif (in_array($k, array('uploading_default_album_visibility', 'uploading_publish_on_captured_date', 'email_handler'))) {
                        $n = new Setting();
                        $n->name = $k;
                        $n->value = $v;
                        $n->save();
                    }
                }

                if (isset($save['email_handler']) || isset($save['email_delivery_address'])) {
                    $this->_compile_plugins();
                }
            }

            $this->redirect('/settings');
        }

        if (!isset($data['site_timezone']) || empty($data['site_timezone']) || $data['site_timezone'] === 'Etc/UTC') {
            $data['site_timezone'] = 'UTC';
        } elseif ($data['site_timezone'] === 'Etc/GMT+12') {
            $data['site_timezone'] = 'Pacific/Auckland';
        }

        $data['image_processing_library_label'] = $libs[$data['image_processing_library']]['label'];

        $migrate_path = FCPATH . 'app' . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR;

        $migrations = scandir($migrate_path);

        $data['migrations'] = [];

        if (!isset($data['last_migration'])) {
            $migration_setting = new Setting();
            $migration_setting->name = 'last_migration';
            $migration_setting->value = '26';
            $migration_setting->save();
            $data['last_migration'] = '26';
        }

        if (!isset($data['has_toured']) || ENVIRONMENT === 'development') {
            $data['has_toured'] = true;
        }

        foreach ($migrations as $migration) {
            $migration = str_replace('.php', '', $migration);
            $migration_int = (int) $migration;
            if ($migration_int > $data['last_migration']) {
                $data['migrations'][] = $migration;
            }
        }

        unset($data['last_migration']);

        $data = Shutter::filter('api.settings', array($data));
        $this->set_response_data($data);
    }
}

/* End of file settings.php */
/* Location: ./system/application/controllers/settings.php */
