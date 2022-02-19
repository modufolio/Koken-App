<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

function koken_rand()
{
	$base = function_exists('mt_rand') ? mt_rand() : rand();
	return md5(uniqid($base, true));
}

function koken_format_tags($tags)
{
	// Some servers don't report PCRE_VERSION as defined even though it is. LOLPHP
	if (@PCRE_VERSION !== 'PCRE_VERSION' && version_compare(PCRE_VERSION, '5.0.0') >= 0)
	{
		$pattern = '/[^\w\p{L}0-9\-\._\s,]+/u';
	}
	else
	{
		$pattern = '/[^\w0-9\-\._\s,]+/u';
	}
	// Strip unwanted characters
	$tags = preg_replace($pattern, '', $tags);
	// Collapse multiple spaces or commas
	$tags = preg_replace('/\s+/', ' ', $tags);
	$tags = preg_replace('/\,+/', ',', $tags);
	// Commafy tags for fast storage/searching in DB
	$tags = ',' . trim($tags) . ',';

	// One last cleanup
	$tags = preg_replace('/\,+/', ',', preg_replace($pattern, '', $tags));
	if (function_exists('mb_strtolower'))
	{
		// strtolower screws up unicode, so use this if avail.
		$tags = mb_strtolower($tags);
	}

	return array_unique( explode(',', trim($tags, ',')) );
}

function create_htaccess($symlink = false, $redirect = false)
{
	if (isset($_SERVER['PHP_SELF']) && isset($_SERVER['SCRIPT_FILENAME']))
	{
		$doc_root = str_replace($_SERVER['PHP_SELF'], '', $_SERVER['SCRIPT_FILENAME']);
	}
	else
	{
		$doc_root = $_SERVER['DOCUMENT_ROOT'];
	}

	$base = trim(preg_replace('/\/api\.php(.*)?$/', '', $_SERVER['SCRIPT_NAME']), '/');
	$doc_base = trim(str_replace($doc_root, '', preg_replace('/\/api\.php(.*)?$/', '', $_SERVER['SCRIPT_FILENAME'])), '/');
	$template = file_get_contents(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'httpd' . DIRECTORY_SEPARATOR . '.htaccess' . ( $redirect ? '-301' : '' ));

	if (empty($doc_base))
	{
		$doc_base = '/';
	}
	else
	{
		$doc_base = "/$doc_base/";
	}

	if ($symlink)
	{
		$php_base = trim($symlink, '/');
		$base_folder_clean = $php_base === '/' ? '/' : "/$php_base";

		$template = preg_replace('~\?url=/(\$1)?~', '?url=/$1&base_folder=' . $base_folder_clean, $template);
	}
	else
	{
		$symlink = '/' . $base;
	}

	if ($redirect)
	{
		$template = str_replace('/$1', ($symlink === '/' ? '/' : "$symlink/") . '$1', $template);
		$template = str_replace('RewriteBase /', 'RewriteBase ' . ($doc_base === '/' ? '/' : rtrim($doc_base, '/')), $template);
		$template = str_replace('%{DOCUMENT_ROOT}/storage', '%{DOCUMENT_ROOT}' . $doc_base . 'storage', $template);
		$template = preg_replace('~\s(storage|app/|i\.php)~', ' ' . $doc_base . '$1', $template);
	}
	else
	{
		if ($symlink !== '/')
		{
			$template = str_replace('^/?$', '^' . $symlink . '/?$', $template);
			$template = str_replace('site/index', 'site' . $symlink . '/index', $template);
		}

		if (!empty($base))
		{
			$base = '/' . $base;
		}

		$template = str_replace('%{DOCUMENT_ROOT}/storage', '%{DOCUMENT_ROOT}' . $doc_base . 'storage', $template);
		$template = preg_replace('~\s(storage|app/|i\.php)~', ' ' . $base . '/$1', $template);
		$template = str_replace('RewriteBase /', 'RewriteBase ' . $symlink, $template);
	}


	if (strpos(strtolower($_SERVER['SERVER_SOFTWARE']), 'ideawebserver') !== false)
	{
		// IdeaWebServer. Why?
		$mime = <<<MIME
:Location *.lens
SetMime text/css
:Location
MIME;
		$template = str_replace('AddType text/css .lens', $mime, $template);
	}

	return $template;
}
/*
	is_callable is not reliable with disable_functions setting in php.ini
*/
function is_really_callable($function_name)
{
	$disabled_functions = explode(',', str_replace(' ', '', ini_get('disable_functions')));

	if (ini_get('suhosin.executor.func.blacklist'))
	{
		$disabled_functions = array_merge($disabled_functions, explode(',', str_replace(' ', '', ini_get('suhosin.executor.func.blacklist'))));
	}

	if (in_array($function_name, $disabled_functions))
	{
		return false;
	}
	else
	{
		return is_callable($function_name);
	}
}

/*
	When creating child directories, make sure parent's permissions are inherited
*/
function make_child_dir($path)
{
	// No need to continue if the directory already exists
	if (is_dir($path)) return true;

	// Make sure parent exists
	$parent = dirname($path);
	if (!is_dir($parent))
	{
		make_child_dir($parent);
	}

	$created = false;
	$old = umask(0);

	// Try to create new directory with parent directory's permissions
	$permissions = substr(sprintf('%o', fileperms($parent)), -4);
	if (is_dir($path) || mkdir($path, octdec($permissions), true))
	{
		$created = true;
	}
	// If above doesn't work, chmod to 777 and try again
	else if (	$permissions == '0755' &&
				chmod($parent, 0777) &&
				mkdir($path, 0777, true)
			)
	{
		$created = true;
	}
	umask($old);
	return $created;
}

function array_merge_custom($arr1, $arr2)
{
	foreach($arr2 as $key => $value)
	{
		if (array_key_exists($key, $arr1) && is_array($value))
		{
			$arr1[$key] = array_merge_custom($arr1[$key], $arr2[$key]);
		}
		else
		{
			$arr1[$key] = $value;
		}
  	}

  	return $arr1;
}

function time_ago($date)
{

	$units = array(
		31556926 => array('%s year ago', '%s years ago'),
		2629744  => array('%s month ago', '%s months ago'),
		604800   => array('%s week ago', '%s weeks ago'),
		86400    => array('%s day ago',  '%s days ago'),
		3600     => array('%s hour ago', '%s hours ago'),
		60       => array('%s min ago',  '%s mins ago'),
	) ;

	$diff = time() - $date;

	foreach($units as $sec => $format)
	{
		if ($diff < $sec)
		{
			continue;
		}

		$units = floor($diff/$sec);
		if ($units > 1)
		{
			$format = $format[1];
		}
		else
		{
			$format = $format[0];
		}
		return sprintf($format, $units);
	}
}

if(!function_exists('directory_copy'))
{
    function directory_copy($srcdir, $dstdir)
    {
        //preparing the paths
        $srcdir=rtrim($srcdir,'/');
        $dstdir=rtrim($dstdir,'/');

        //creating the destination directory
        if(!is_dir($dstdir))mkdir($dstdir, 0777, true);

        //Mapping the directory
        $dir_map=directory_map($srcdir);

        foreach($dir_map as $object_key=>$object_value)
        {
            if(is_numeric($object_key))
                copy($srcdir.'/'.$object_value,$dstdir.'/'.$object_value);//This is a File not a directory
            else
                directory_copy($srcdir.'/'.$object_key,$dstdir.'/'.$object_key);//this is a directory
        }
    }
}