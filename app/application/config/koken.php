<?php

 if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

if (isset($_SERVER['HTTP_HOST'])) {
    $__protocol = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
        $_SERVER['SERVER_PORT'] == 443 ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? 'https' : 'http';
    $__full =  $__protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $array = explode('api.php', $__full);
    $__base = array_shift($array);
    $__rel = str_replace($__protocol . '://' . $_SERVER['HTTP_HOST'], '', $__full);
    $__root = str_replace($__protocol . '://' . $_SERVER['HTTP_HOST'], '', $__base);
    $__obj = new stdClass();
    $__obj->full = $__full;
    $__obj->base = $__base;
    $__obj->relative = $__rel;
    $__obj->relative_base = $__root;
    $config['koken_url_info'] = $__obj;
} else {
    $config['koken_url_info'] = 'unknown';
}

$key = Shutter::get_encryption_key();

if ($key) {
    $config['encryption_key'] = $key;
}

if (!defined('MAGICK_PATH')) {
    define('MAGICK_PATH_FINAL', 'convert');
} elseif (strpos(strtolower(MAGICK_PATH), 'c:\\') !== false) {
    define('MAGICK_PATH_FINAL', '"' . MAGICK_PATH . '"');
} else {
    define('MAGICK_PATH_FINAL', MAGICK_PATH);
}

if (!defined('FFMPEG_PATH')) {
    define('FFMPEG_PATH_FINAL', 'ffmpeg');
} else {
    define('FFMPEG_PATH_FINAL', FFMPEG_PATH);
}

if (!defined('AUTO_UPDATE')) {
    define('AUTO_UPDATE', true);
}

// Director constants
define('KOKEN_VERSION', '1.1.4.2');

/* End of file koken.php */
/* Location: ./system/application/config/koken.php */
