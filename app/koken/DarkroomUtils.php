<?php

class DarkroomUtils {

	public static $presets = array(
		'tiny' => array(
			'width' => 60,
			'height' => 60
		),
		'small' => array(
			'width' => 100,
			'height' => 100
		),
		'medium' => array(
			'width' => 480,
			'height' => 480
		),
		'medium_large' => array(
			'width' => 800,
			'height' => 800
		),
		'large' => array(
			'width' => 1024,
			'height' => 1024
		),
		'xlarge' => array(
			'width' => 1600,
			'height' => 1600
		),
		'huge' => array(
			'width' => 2048,
			'height' => 2048
		)
	);

	public static function init($library)
	{
		$root = dirname(dirname(dirname(__FILE__))) . '/app/koken/Darkroom/Darkroom';

		require_once($root . '.php');

		$limits = array(
			'thread' => defined('DARKROOM_MAGICK_THREADS') ? DARKROOM_MAGICK_THREADS : 1,
			'memory' => defined('DARKROOM_MAGICK_MEMORY') ? DARKROOM_MAGICK_MEMORY : 67108864,
			'map' => defined('DARKROOM_MAGICK_MAP') ? DARKROOM_MAGICK_MAP : 128217728,
		);

		if ($library === 'imagick')
		{
			require_once($root . 'Imagick.php');
			$d = new DarkroomImagick($limits);
		}
		else if (strpos($library, 'convert') !== false)
		{
			require_once($root . 'ImageMagick.php');
			$d = new DarkroomImageMagick($library, $limits);
		}
		else
		{
			require_once($root . 'GD2.php');
			$d = new DarkroomGD2;
		}

		return $d;
	}

	private static function isCallable($function_name)
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

	static function libraries()
	{
		$libraries = array();

		if (in_array('imagick', get_loaded_extensions()) && class_exists('Imagick'))
		{
			$im = new Imagick;
			$version = $im->getVersion();
			preg_match('/\d+\.\d+\.\d+([^\s]+)?/', $version['versionString'], $matches);

			$libraries['imagick'] = array(
				'key' => 'imagick',
				'label' => 'Imagick ' . $matches[0]
			);
		}

		if (self::isCallable('shell_exec'))
		{
			$commonPaths = array(
				'convert', '/usr/bin', '/usr/local/bin', '/usr/local/sbin', '/bin', '/opt/local/bin', '/opt/ImageMagick/bin', '/usr/local/ImageMagick/bin'
			);

			if (defined('MAGICK_PATH_FINAL') && MAGICK_PATH_FINAL !== 'convert')
			{
				array_unshift($commonPaths, MAGICK_PATH_FINAL);
			}

			function testShell($path)
			{
				$out = shell_exec($path . ' -version 2');

				if (!empty($out) && preg_match('/\d+\.\d+\.\d+/', $out, $matches)) {
					return array('key' => $path, 'label' => $matches[0]);
				}

				return false;
			}

			function newestImageMagick($arr)
			{
				$top = array_shift($arr);
				foreach($arr as $m)
				{
					if (version_compare($m['label'], $top['label']) > 0)
					{
						$top = $m;
					}
				}

				return $top;
			}


			$imagemagick = $graphicsmagick = array();

			foreach($commonPaths as $path)
			{
				if (!preg_match('/convert$/', $path))
				{
					$path = rtrim($path, '/') . '/convert';
				}

				$im = testShell($path);

				if ($im)
				{
					$im['label'] = 'ImageMagick ' . $im['label'];
					$imagemagick[] = $im;
				}

				$gm = testShell(str_replace('convert', 'gm convert', $path));

				if ($gm)
				{
					$gm['label'] = 'GraphicsMagick ' . $gm['label'];
					$graphicsmagick[] = $gm;
				}
			}

			$imagemagick = newestImageMagick($imagemagick);
			$graphicsmagick = newestImageMagick($graphicsmagick);

			if ($imagemagick)
			{
				$libraries[$imagemagick['key']] = $imagemagick;
			}

			if ($graphicsmagick)
			{
				$libraries[$graphicsmagick['key']] = $graphicsmagick;
			}
		}

		// Need to implement this first

		// if (in_array('gmagick', get_loaded_extensions()) && class_exists('Gmagick'))
		// {
		// 	$im = new Gmagick;
		// 	$version = $im->getVersion();
		// 	preg_match('/\d+\.\d+\.\d+([^\s]+)?/', $version['versionString'], $matches);

		// 	$libraries['gmagick'] = array(
		// 		'key' => 'gmagick',
		// 		'label' => 'Gmagick ' . $matches[0]
		// 	);
		// }

		if (function_exists('gd_info')) {
			$gd = gd_info();
			$libraries['gd'] = array(
				'key' => 'gd',
				'label' =>'GD ' . $gd['GD Version']
			);
		}

		return $libraries;
	}

	static function detect($force = false) {

		$cache = dirname(dirname(dirname(__FILE__))) . '/storage/cache/images/provider.cache';

		if (!$force && file_exists($cache))
		{
			$cache = unserialize(file_get_contents($cache));
			if ($cache && count($cache) === 2)
			{
				return $cache;
			}
		}

		$versionString = $className = false;

		if (function_exists('gd_info')) {
			$gd = gd_info();
			$versionString = 'GD ' . $gd['GD Version'];
			$className = 'DarkroomGD2';
		}

		if (MAGICK_PATH_FINAL !== 'gd')
		{
			if (in_array('imagick', get_loaded_extensions()) && class_exists('Imagick') && MAGICK_PATH_FINAL === 'convert')
			{
				$im = new Imagick;
				$version = $im->getVersion();
				preg_match('/\d+\.\d+\.\d+([^\s]+)?/', $version['versionString'], $matches);
				$versionString = 'Imagick ' . $matches[0];
				$className = 'DarkroomImagick';
			}
			else if (self::isCallable('shell_exec') && (DIRECTORY_SEPARATOR == '/' || (DIRECTORY_SEPARATOR == '\\' && MAGICK_PATH_FINAL != 'convert'))) {
				$out = shell_exec(MAGICK_PATH_FINAL . ' -version');
				preg_match('/(?:Image|Graphics)Magick\s(\d+\.\d+\.\d+([^\s]+))?/', $out, $matches);
				if ($matches)
				{
					$versionString = $matches[0];
					$className = 'DarkroomImageMagick';
				}
			}
		}

		$arr = array($versionString, $className);

		file_put_contents($cache, serialize($arr));

		return $arr;
	}
}