<?php

class FFmpeg {
	var $path;
	var $ffmpeg = 'ffmpeg';
	var $info = null;
	var $duration = 0;
	var $dimensions = 0;

	function __construct($path = false) {
		$this->path = $path;
		$this->ffmpeg = FFMPEG_PATH_FINAL;
	}

	function version() {
		if (function_exists('exec') && (DIRECTORY_SEPARATOR == '/' || (DIRECTORY_SEPARATOR == '\\' && $this->ffmpeg != 'ffmpeg'))) {
			exec($this->ffmpeg . ' -version 2>&1', $out);
			if (empty($out)) {
				return false;
			} else {
				if (strpos(strtolower($out[0]), 'ffmpeg') !== false && preg_match('/(\d+.\d+(.\d+)?)/', $out[0], $matches)) {
					return $matches[1];
				} else {
					return false;
				}
			}
		}
		return false;
	}

	function check() {
		return file_exists($this->path);
	}

	function info() {
		exec($this->ffmpeg . " -i \"{$this->path}\" 2>&1", $this->info);
	}

	function create_thumbs()
	{
		$target_directory = dirname($this->path) . DIRECTORY_SEPARATOR . basename($this->path) . '_previews' . DIRECTORY_SEPARATOR;
		make_child_dir($target_directory);
		$duration = $this->duration() - 2;
		$bits = ceil($duration/12);
		if ($bits == 0) {
			$bits = 1;
		}
		$rate = 1/$bits;
		if ($rate < 0.1) {
			$rate = 0.1;
		}

		$i = 1;
		$cmd = array();
		while ($i < $duration) {
			$i_str = str_pad($i, 5, '0', STR_PAD_LEFT);
			$cmd[] = $this->ffmpeg . " -ss $i -i \"{$this->path}\" -vframes 1 -an -f mjpeg \"$i_str.jpg\"";
			$i += $bits;
		}

		chdir($target_directory);
		if (DIRECTORY_SEPARATOR == '\\') {
			foreach($cmd as $c) {
				exec($c);
			}
		} else {
			$cmd = join(' && ', $cmd);
			exec($cmd);
		}

		$files = directory_map($target_directory, true);
		if ($files)
		{
			return $files[ max(0, floor(count($files)/2)-1) ] . ':50:50';
		}
		else
		{
			return null;
		}
	}

	function dimensions() {
		if (is_null($this->info)) {
			$this->info();
		}

		foreach($this->info as $line) {
			if (strpos($line, 'Video:') !== false) {
				preg_match('/([0-9]{2,5})x([0-9]{2,5})/', $line, $matches);
				list(,$w, $h) = $matches;
				$this->dimensions = array($w, $h);
				return $this->dimensions;
			}
		}
	}

	function duration() {
		if ($this->duration > 0) {
			return $this->duration;
		}

		if (is_null($this->info)) {
			$this->info();
		}

		foreach($this->info as $line) {
			if (strpos($line, 'Duration:') !== false) {
				preg_match('/Duration: ([0-9]{2}):([0-9]{2}):([0-9]{2})/', $line, $matches);
				list(,$h, $m, $s) = $matches;
				$this->duration = ($h*60*60) + ($m*60) + $s;
				return $this->duration;
			}
		}
	}
}

?>