<?php

class Shutter
{
    public static $active_pulse_plugins = [];
    public static $custom_sources = [];

    private static $filters = [];
    private static $hooks = [];
    private static $shortcodes = [];
    private static $plugin_info = [];
    private static $scripts = [];
    private static $active_plugins = [];
    private static $loaded_plugins = [];
    private static $class_map = [];
    private static $cache_providers = array(
        'api' => false,
        'core' => false,
        'site' => false,
        'images' => false,
        'locks' => false,
        'plugins' => false,
        'icc' => false,
        'albums' => false,
    );

    private static $email_provider = false;
    private static $email_providers = [];
    private static $email_delivery_address = false;

    private static $db_config_provider = false;
    private static $encryption_key_provider = false;
    private static $original_storage_handler = false;
    private static $template_folders = [];
    private static $messages = [];
    private static $body_classes = [];

    private static function plugin_is_active($callback)
    {
        return in_array(get_class($callback[0]), self::$active_plugins);
    }

    public static function get_php_object($class_name)
    {
        return self::$class_map[$class_name];
    }

    public static function get_json_api($url, $to_json = true)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Koken/' . KOKEN_VERSION);
        $info = curl_exec($curl);
        curl_close($curl);

        if ($to_json) {
            return json_decode($info);
        } else {
            return $info;
        }
    }

    public static function get_encryption_key()
    {
        if (self::$encryption_key_provider) {
            return call_user_func(array(
                self::$encryption_key_provider, 'get'
            ));
        }
    }

    public static function write_encryption_key($key)
    {
        if (self::$encryption_key_provider) {
            return call_user_func(array(
                self::$encryption_key_provider, 'write'
            ), $key);
        }
    }

    public static function get_db_configuration()
    {
        if (self::$db_config_provider) {
            return call_user_func(array(
                self::$db_config_provider, 'get'
            ));
        }
    }

    public static function write_db_configuration($config)
    {
        if (self::$db_config_provider) {
            return call_user_func(array(
                self::$db_config_provider, 'write'
            ), $config);
        }
    }

    public static function email($from, $from_name, $to, $subject, $message, $handler = null)
    {
        if (is_null($to)) {
            $to = self::$email_delivery_address;
        }

        if (is_null($handler)) {
            $handler = self::$email_provider;
        } else {
            $handler = self::$class_map[$handler];
        }

        if (self::$email_provider) {
            return call_user_func(array(
                $handler, 'send'
            ), $from, $from_name, $to, $subject, $message);
        }
    }

    private static function cache_type($path)
    {
        $slash = strpos($path, '/');

        if ($slash === false) {
            return $path;
        }

        return substr($path, 0, strpos($path, '/'));
    }

    public static function get_cache($path, $allow_304 = true)
    {
        $type = self::$cache_providers[Shutter::cache_type($path)];
        if ($type) {
            return call_user_func(array(
                $type, 'get'
            ), $path, $allow_304);
        }

        return false;
    }

    public static function write_cache($path, $content)
    {
        $type = self::$cache_providers[Shutter::cache_type($path)];
        if ($type) {
            call_user_func(array(
                $type, 'write'
            ), $path, $content);
        }
    }

    public static function add_body_class($class)
    {
        if (in_array($class, self::$body_classes)) {
            return;
        }

        self::$body_classes[] = $class;
    }

    public static function get_body_classes()
    {
        return self::$body_classes;
    }

    public static function clear_cache($path)
    {
        if (!is_array($path)) {
            $path = array($path);
        }

        foreach ($path as $p) {
            $type = self::$cache_providers[Shutter::cache_type($p)];

            if ($type) {
                call_user_func(array(
                    $type, 'clear'
                ), $p);
            }
        }
    }

    public static function get_oembed($url)
    {
        if (!defined('FCPATH')) {
            return false;
        } // Shouldn't be called outside of API context
        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        $qs = [];

        foreach ($query as $arg => $val) {
            $qs[] = $arg . '=' . urlencode($val);
        }

        $parts = explode('?', $url);
        $url = $parts[0] . '?' . implode('&', $qs);

        $url = preg_replace('~^http://www\.flickr\.com~', 'https://www.flickr.com', $url);
        $url = preg_replace('~^http://api\.instagram\.com/oembed\?~', 'https://api.instagram.com/oembed/?', $url);

        $hash = md5($url) . '.oembed.cache';
        $cache = FCPATH . 'storage' . DIRECTORY_SEPARATOR .
                    'cache' . DIRECTORY_SEPARATOR .
                    'api' . DIRECTORY_SEPARATOR . $hash;

        if (file_exists($cache) && (time() - filemtime($cache)) < 3600) {
            $info = file_get_contents($cache);
            $json = json_decode($info, true);
        } else {
            $json_string = self::get_json_api($url, false);
            $json = json_decode($json_string, true);
            if ($json && !isset($json->error)) {
                file_put_contents($cache, $json_string);
            }
        }

        return $json;
    }

    public static function call_method($klass, $method, $arg = null)
    {
        if (method_exists(self::$class_map[$klass], $method)) {
            return self::$class_map[$klass]->$method($arg);
        }

        return false;
    }

    public static function enable()
    {
        require_once 'KokenPlugin.php';
        require_once 'Contracts/KokenCache.php';
        require_once 'Contracts/KokenEmail.php';
        require_once 'Contracts/KokenDatabaseConfiguration.php';
        require_once 'Contracts/KokenEncryptionKey.php';
        require_once 'Contracts/KokenOriginalStore.php';

        $root = dirname(dirname(dirname(dirname(__FILE__))));

        self::scan('app/plugins', true, true);

        if (getenv('KOKEN_SHUTTER_AUTOLOAD')) {
            self::scan(getenv('KOKEN_SHUTTER_AUTOLOAD'), true, true);
        }

        $compiled = self::get_cache('plugins/compiled.cache');

        if (!$compiled && strpos($_SERVER['QUERY_STRING'], 'plugins/compile') === false) {
            include dirname(__DIR__) . '/Utils/KokenAPI.php';
            $api = new KokenAPI();
            $api->get('/plugins/compile');
        }

        $compiled = self::get_cache('plugins/compiled.cache');

        Shutter::$email_provider = self::$class_map['DDI_Email'];

        if ($compiled) {
            $compiled_plugins = unserialize($compiled['data']);
            foreach ($compiled_plugins['plugins'] as $plugin) {
                self::parse($root . '/storage/plugins/' . $plugin['path'], true, false, isset($plugin['data']) ? $plugin['data'] : array());
            }

            if (isset($compiled_plugins['info']['email_handler']) && isset(self::$class_map[$compiled_plugins['info']['email_handler']])) {
                self::$email_provider = self::$class_map[$compiled_plugins['info']['email_handler']];
            }

            if (isset($compiled_plugins['info']['email_delivery_address'])) {
                self::$email_delivery_address = $compiled_plugins['info']['email_delivery_address'];
            }
        }
    }

    private static function parse($dir, $activate = false, $internal = false, $plugin_data = array())
    {
        $path = basename($dir);

        if (in_array($path, self::$loaded_plugins)) {
            return;
        }

        $plugin = $dir. '/plugin.php';
        $pulse = $dir. '/pulse.json';
        $info = $dir. '/plugin.json';
        $guid = $dir. '/koken.guid';
        $console = file_exists($dir . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . 'plugin.js');
        $data = false;

        if (file_exists($info)) {
            $data = json_decode(file_get_contents($info), true);
            if ($data) {
                if (isset($data['custom_sources'])) {
                    foreach ($data['custom_sources'] as $name => $filters) {
                        self::$custom_sources[$name] = $filters;
                    }
                }

                if (!file_exists($plugin) && !isset($data['oembeds'])) {
                    return;
                }
                $data['path'] = $path;
                if (file_exists($plugin)) {
                    $raw_plugin = file_get_contents($plugin);
                    preg_match('/class\s([^\s]+)\sextends\sKokenPlugin/m', $raw_plugin, $matches);

                    if ($matches && !class_exists($matches[1])) {
                        $test = include_once $plugin;
                        if ($test === true) {
                            return;
                        }
                        $klasses = get_declared_classes();
                        $last = array_pop($klasses);
                        $data['php_class_name'] = $last;

                        if ($activate) {
                            self::$active_plugins[] = $last;
                        }

                        self::$class_map[$last] = new $last();

                        if ($activate) {
                            if (isset($data_sidecar) && file_exists($data_sidecar)) {
                                self::$class_map[$last]->set_data(unserialize(file_get_contents($data_sidecar)));
                            } elseif (!empty($plugin_data)) {
                                self::$class_map[$last]->set_data($plugin_data);
                            }
                        }
                    } else {
                        return;
                    }
                }

                $data['pulse'] = false;
                $data['console'] = $console;
            }
        } elseif (file_exists($pulse)) {
            $data = json_decode(file_get_contents($pulse), true);
            $data['path'] = $path;
            $data['plugin'] = '/storage/plugins/' . $data['path'] . '/' . $data['plugin'];
            $data['pulse'] = true;
            $data['ident'] = $data['id'];

            if ($activate) {
                self::$active_pulse_plugins[] = array(
                    'key' => $data['id'],
                    'path' => $data['plugin']
                );
            }
        }

        if (file_exists($guid)) {
            $data['koken_store_guid'] = trim(file_get_contents($guid));
        }

        if ($data) {
            self::$loaded_plugins[] = $path;
            $data['internal'] = $internal;
            $data['activated'] = $activate;
            self::$plugin_info[] = $data;
        }
    }

    private static function scan($directory, $activate = false, $internal = false)
    {
        $root = dirname(dirname(dirname(dirname(__FILE__))));

        if (substr($directory, 0, 1) !== '/') {
            $directory = $root . '/' . $directory;
        }

        if (!is_dir($directory)) {
            return;
        }

        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->getFilename() === 'index.html' || !$fileinfo->isDir() || $fileinfo->isDot()) {
                continue;
            }
            $dir = $fileinfo->getPath() . '/' . $fileinfo->getFilename();
            self::parse($dir, $activate, $internal);
        }
    }
    public static function all($map)
    {
        self::scan("storage/plugins");

        $final = [];

        foreach (self::$plugin_info as $plugin) {
            if (isset($map[$plugin['path']]) || $plugin['internal']) {
                $plugin['activated'] = true;

                if (!$plugin['internal']) {
                    $plugin['id'] = $map[$plugin['path']]['id'];
                    $plugin['setup'] = $map[$plugin['path']]['setup'];
                    $saved = $map[$plugin['path']]['data'];
                }

                if (isset($plugin['data'])) {
                    foreach ($plugin['data'] as $key => &$d) {
                        if (isset($saved[$key])) {
                            $d['value'] = $saved[$key];
                        }
                    }
                    if ($d['type'] === 'boolean' && isset($d['value'])) {
                        $d['value'] = $d['value'] == 'true';
                    }
                }
            } else {
                $plugin['setup'] = $plugin['activated'] = false;
            }

            if (isset($plugin['php_class_name'])) {
                $plugin['compatible'] = Shutter::call_method($plugin['php_class_name'], 'is_compatible');
                if ($plugin['compatible'] !== true) {
                    $plugin['compatible_error'] = $plugin['compatible'];
                    $plugin['compatible'] = false;
                }
            } else {
                $plugin['compatible'] = true;
            }

            $final[] = $plugin;
        }

        return $final;
    }

    public static function hook($name, $obj = null)
    {
        if (!isset(self::$hooks[$name])) {
            return;
        }

        $to_call = self::$hooks[$name];
        if (!empty($to_call)) {
            foreach ($to_call as $callback) {
                if (self::plugin_is_active($callback)) {
                    if (is_array($obj) && !isset($obj['__koken__'])) {
                        $data = call_user_func_array($callback, $obj);
                    } else {
                        $data = call_user_func($callback, $obj);
                    }
                }
            }
        }
    }

    public static function shortcodes($content, $args)
    {
        $scripts = [];

        preg_match_all('/\[([a-z_]+)(\s(.*?))?\]/', $content, $matches);

        foreach ($matches[0] as $index => $match) {
            $tag = $match;
            $code = $matches[1][$index];
            $attr = $matches[3][$index];
            if (isset(self::$shortcodes[$code]) && self::plugin_is_active(self::$shortcodes[$code])) {
                if (!empty($attr)) {
                    preg_match_all('/([a-z_]+)="([^"]+)?"/', $attr, $attrs);
                    $attr = array_combine($attrs[1], $attrs[2]);
                }
                $array = explode('api.php', $_SERVER['PHP_SELF']);
                $attr['_relative_root'] = array_shift($array);

                foreach ($attr as $key => &$val) {
                    $val = str_replace(array('__quot__', '__lt__', '__gt__', '__n__', '__lb__', '__rb__', '__perc__'), array('"', '<', '>', "\n", '[', ']', '%'), $val);
                }

                $filtered = call_user_func(self::$shortcodes[$code], $attr);
                if (is_array($filtered)) {
                    $replacement = $filtered[0];
                    if (empty($filtered[1])) {
                        $filtered[1] = [];
                    } elseif (!is_array($filtered[1])) {
                        $filtered[1] = array($filtered[1]);
                    }
                    foreach ($filtered[1] as $script) {
                        if (!in_array($script, $scripts)) {
                            $scripts[] = $script;
                        }
                    }
                } else {
                    $replacement = $filtered;
                }
                $content = str_replace($tag, $replacement, $content);
            }
        }

        if (!empty($scripts)) {
            $array1 = explode('/api.php', $_SERVER['REQUEST_URI']);
            $base = array_shift($array1);
            foreach ($scripts as &$script) {
                $script = '<script src="' . $base . $script . '"></script>';
            }
            $content = implode('', $scripts) . $content;
        }
        return $content;
    }

    public static function filter($name, $args)
    {
        $data = is_array($args) && isset($args[0]) ? array_shift($args) : $args;

        if (!isset(self::$filters[$name])) {
            return $data;
        }

        $to_call = self::$filters[$name];

        if (!empty($to_call)) {
            foreach ($to_call as $callback) {
                if (self::plugin_is_active($callback)) {
                    if (is_array($args)) {
                        $data = call_user_func_array($callback, array_values(array_merge(array($data), $args)));
                    } else {
                        $data = call_user_func($callback, $data);
                    }
                }
            }
        }

        return $data;
    }

    public static function register_hook($name, $arr)
    {
        if (!isset(self::$hooks[$name])) {
            self::$hooks[$name] = [];
        }

        if (in_array($arr, self::$hooks[$name])) {
            return;
        }

        self::$hooks[$name][] = $arr;
    }

    public static function register_filter($name, $arr)
    {
        if (!isset(self::$filters[$name])) {
            self::$filters[$name] = [];
        }

        if (in_array($arr, self::$filters[$name])) {
            return;
        }

        self::$filters[$name][] = $arr;
    }

    public static function register_shortcode($name, $arr)
    {
        if (!isset(self::$shortcodes[$name])) {
            self::$shortcodes[$name] = $arr;
        }
    }

    public static function register_site_script($path, $plugin)
    {
        $item = array('path' => $path, 'plugin' => $plugin);

        if (!in_array($item, self::$scripts)) {
            self::$scripts[] = $item;
        }
    }

    public static function register_cache_handler($handler, $target)
    {
        if (in_array(get_class($handler), self::$active_plugins) && in_array('KokenCache', class_implements($handler))) {
            if ($target === 'all') {
                $target = array('site', 'api', 'core', 'images', 'locks', 'plugins', 'icc', 'albums');
            }

            if (!is_array($target)) {
                $target = array($target);
            }

            foreach ($target as $t) {
                self::$cache_providers[$t] = $handler;
            }
        }
    }

    public static function register_email_handler($handler, $label)
    {
        $class = get_class($handler);

        if (in_array($class, self::$active_plugins) && in_array('KokenEmail', class_implements($handler))) {
            self::$email_providers[get_class($handler)] = compact('class', 'label', 'handler');
        }
    }

    public static function get_email_handlers()
    {
        return array_map(function ($info) {
            return array(
                'class' => $info['class'],
                'label' => $info['label']
            );
        }, array_values(self::$email_providers));
    }

    public static function register_db_config_handler($handler)
    {
        if (in_array(get_class($handler), self::$active_plugins) && in_array('KokenDatabaseConfiguration', class_implements($handler))) {
            self::$db_config_provider = $handler;
        }
    }

    public static function register_encryption_key_handler($handler)
    {
        if (in_array(get_class($handler), self::$active_plugins) && in_array('KokenEncryptionKey', class_implements($handler))) {
            self::$encryption_key_provider = $handler;
        }
    }

    public static function register_storage_handler($handler)
    {
        if (in_array(get_class($handler), self::$active_plugins) && in_array('KokenOriginalStore', class_implements($handler))) {
            self::$original_storage_handler = $handler;
        }
    }

    public static function register_template_folder($handler, $path)
    {
        if (is_array($path)) {
            foreach ($path as $p) {
                return self::register_template_folder($handler, $p);
            }
        }

        if (in_array(get_class($handler), self::$active_plugins) && is_dir($path)) {
            self::$template_folders[] = $path;
        }
    }

    public static function set_message($key, $msg)
    {
        if (class_exists('Koken')) {
            Koken::$messages[$key] = $msg;
        }

        self::$messages[$key] = $msg;
    }

    public static function get_messages()
    {
        return self::$messages;
    }

    public static function get_template_folders()
    {
        return self::$template_folders;
    }

    public static function store_original($localFile, $content)
    {
        if (self::$original_storage_handler) {
            return call_user_func(array(
                self::$original_storage_handler, 'send'
            ), $localFile, $content);
        }

        return false;
    }

    public static function delete_original($url)
    {
        if (self::$original_storage_handler) {
            return call_user_func(array(
                self::$original_storage_handler, 'delete'
            ), $url);
        }

        return false;
    }

    private static function get_active_site_script_paths()
    {
        $scripts = [];

        foreach (self::$scripts as $arr) {
            if (self::plugin_is_active(array($arr['plugin'])) && file_exists($arr['path'])) {
                $scripts[] = $arr['path'];
            }
        }

        return $scripts;
    }

    public static function get_site_scripts()
    {
        $scripts = self::get_active_site_script_paths();

        $output = [];
        foreach ($scripts as $path) {
            $output[] = file_get_contents($path);
        }

        return $output;
    }

    public static function get_site_scripts_timestamp()
    {
        $scripts = self::get_active_site_script_paths();

        if (empty($scripts)) {
            return KOKEN_VERSION;
        }

        return md5(implode('', $scripts));

    }

    public static function hook_exists($name)
    {
        if (!isset(self::$hooks[$name]) || empty(self::$hooks[$name])) {
            return false;
        }

        foreach (self::$hooks[$name] as $callback) {
            if (self::plugin_is_active($callback)) {
                return true;
            }
        }

        return false;
    }
}
