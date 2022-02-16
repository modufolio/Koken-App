<?php

class GPS {

	private $exif;
	
	function __construct($exif)
	{
		$this->exif = $exif;
	}
	
	function latitude()
	{
		return $this->convert($this->exif['GPSLatitude'], $this->exif['GPSLatitudeRef']);
	}
	
	function longitude()
	{
		return $this->convert($this->exif['GPSLongitude'], $this->exif['GPSLongitudeRef']);		
	}
	
	private function convert($arr, $quadrant)
	{
		$d = $this->divide($arr[0]);
		$m = $this->divide($arr[1]);
		$s = $this->divide($arr[2]);
		$dec = ((($s/60)+$m)/60) + $d;
		if (strtolower($quadrant) == 's' || strtolower($quadrant) == 'w') {
			$dec = -$dec;	
		}
		return $dec;
	}
	
	private function divide($str)
	{
		$bits = explode('/', $str);
		$dec = $bits[0] / $bits[1];
		return $dec;
	}
	
}