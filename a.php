<?php

  date_default_timezone_set('UTC');

  ini_set('max_execution_time', 600);
  ini_set('display_errors', 1);
  error_reporting(1);

  set_time_limit(600);

  $root = dirname(__FILE__);

  @include $root . '/storage/configuration/user_setup.php';
  require_once $root . '/app/koken/Shutter/Shutter.php';
  require_once $root . '/app/koken/Utils/KokenAPI.php';
  require $root . '/app/application/libraries/phpzip/autoload.php';

  if (!defined('LOOPBACK_HOST_HEADER'))
  {
    define('LOOPBACK_HOST_HEADER', false);
  }

  Shutter::enable();
  Shutter::hook('image.boot');

  if (isset($_GET['path']))
  {
    $path = $_GET['path'];
  }
  else if (isset($_SERVER['QUERY_STRING']))
  {
    $path = urldecode($_SERVER['QUERY_STRING']);
  }
  else if (isset($_SERVER['PATH_INFO']))
  {
    $path = $_SERVER['PATH_INFO'];
  }
  else if (isset($_SERVER['REQUEST_URI']))
  {
    $path = preg_replace('/.*\/a.php/', '', $_SERVER['REQUEST_URI']);
  }

  $ds = DIRECTORY_SEPARATOR;

  // File exists, but rewites not supported
  if (file_exists($root . '/storage/cache/albums' . $path))
  {
    $file = $root . '/storage/cache/albums' . $path;

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));

    $disabled_functions = explode(',', ini_get('disable_functions'));

    if (is_callable('readfile') && !in_array('readfile', $disabled_functions))
    {
      readfile($file);
      exit;
    }
    else
    {
      die(file_get_contents($file));
    }
  }

  preg_match('/^\/([0-9]{3}\/[0-9]{3})\/[0-9]+\/.+$/i', $path, $matches);

  if (empty($matches))
  {
    // Bad request
    header('HTTP/1.1 403 Forbidden');
    exit;
  }

  $id = (int) str_replace('/', '', $matches[1]);

  $KokenAPI = new KokenAPI;
  $result = $KokenAPI->get('/albums/' . $id . '/content/limit:500');

  if (!isset($result['album']) || $result['album']['album_type'] === 'set')
  {
    header('HTTP/1.1 403 Forbidden');
    exit;
  }

  $visibility_values = array('public', 'unlisted', 'private');
  $album_visibility = array_search($result['album']['visibility']['raw'], $visibility_values);

  $ts = $result['album']['modified_on']['timestamp'];
  $album_cache_key = 'albums/' . $matches[1];
  $cache_key = $album_cache_key . '/' . $ts . '/' . basename($path);

  Shutter::clear_cache($album_cache_key);

  $zip = new \PHPZip\Zip\File\Zip();

  // Create an empty file
  Shutter::write_cache($cache_key, '');
  $zip->setZipFile($root . '/storage/cache/' . $cache_key);

  foreach($result['content'] as $content)
  {
    $md = $content['max_download']['raw'];
    $visibility = array_search($content['visibility']['raw'], $visibility_values);

    if ($md !== 'none' && $visibility <= $album_visibility)
    {
      if ($md === 'original')
      {
        $url = $root . $content['original']['relative_url'];
      }
      else
      {
        $url = $content['presets'][$md]['url'];
        $relative_url = preg_replace('/.+?(?=\/storage\/cache\/images\/)/', $root, $url);

        if (file_exists($relative_url))
        {
          $url = $relative_url;
        }
      }

      $file = false;
      for ($i = 1; $i <= 10; $i++) {
        $file = file_get_contents($url);

        if ($file) {
          break;
        } else {
          sleep(1);
        }
      }

      if ($file)
      {
        $zip->addFile($file, $content['filename']);
      }
    }
  }

  if ($zip->getArchiveSize() !== 0)
  {
    $zip->sendZip(basename($path));
  }
  else
  {
    Shutter::clear_cache($album_cache_key);
  }
