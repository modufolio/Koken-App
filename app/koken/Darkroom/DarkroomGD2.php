<?php

class DarkroomGD2 extends Darkroom {

	private $sourceImage = false;
	private $sourceType;
	private $finalImage;

	function __construct()
	{
		$memory_limit = (int) ini_get('memory_limit');

		if (is_numeric($memory_limit) && $memory_limit < 256)
		{
			ini_set('memory_limit', '256M');
		}
	}

	public function getQuality() {
		return 100;
	}

	public function rotate($path, $degrees)
	{
		$this->createSource($path);
		$this->finalImage = imagerotate($this->sourceImage, -$degrees, 0);

		imagedestroy($this->sourceImage);

		$this->path = $path;
		$this->quality = 100;
		$this->output();

		return $this;
	}

	public function createImage()
	{
		$this->createSource();
		$this->finalImage = $this->createProportionalCopy($this->width, $this->height);
		return $this->output();
	}

	public function createCroppedImage($interstitialWidth, $interstitialHeight, $cropX, $cropY)
	{
		$this->createSource();

		$interstitial = $this->createProportionalCopy($interstitialWidth, $interstitialHeight);

		$this->finalImage = imagecreatetruecolor($this->width, $this->height);

		if ($this->sourceType == IMAGETYPE_PNG)	{
			$transparency = imagecolorallocatealpha($final, 0, 0, 0, 127);
			imagefill($final, 0, 0, $transparency);
		}

		imagecopy($this->finalImage, $interstitial, 0, 0, $cropX, $cropY, $this->width, $this->height);
		imagedestroy($interstitial);

		return $this->output();
	}

	private function applySharpening()
	{
		if (!function_exists('imageconvolution')) return;

		if ($this->sharpening !== 1) {
			$this->sharpening = abs(1 - $this->sharpening);
		}

		$matrix = array(
			array(-1, -1, -1),
			array(-1, ceil($this->sharpening*60), -1),
			array(-1, -1, -1),
		);

		$divisor = array_sum(array_map('array_sum', $matrix));

		imageconvolution($this->finalImage, $matrix, $divisor, 0);
	}

	private function output()
	{
		if ($this->sharpening !== false) {
			$this->applySharpening();
		}

		$this->finalImage = $this->emitBeforeRender($this->finalImage);

		$path = $this->path;

		if (!$this->path) {
			ob_start();
			$path = null;
		}

		if ($this->sourceType === IMAGETYPE_PNG) {
			imagealphablending($this->finalImage, false);
			imagesavealpha($this->finalImage, true);
			imagepng($this->finalImage, $path);
		} elseif ($this->sourceType === IMAGETYPE_GIF) {
			imagegif($this->finalImage, $path);
		} else {
			imagejpeg($this->finalImage, $path, min($this->quality, 99));
		}

		if (!$this->path) {
			$data = ob_get_contents();
			ob_end_clean();
			imagedestroy($this->finalImage);

			return $data;
		}

		imagedestroy($this->finalImage);
	}

	private function createSource($path = false)
	{
		if (!$path) {
			$path = $this->sourcePath;
		}

		list(,,$this->sourceType) = getimagesize($path);

		switch($this->sourceType) {
			case IMAGETYPE_JPEG:
				$this->sourceImage = imagecreatefromjpeg($path);
				break;

			case IMAGETYPE_PNG:
				$this->sourceImage = imagecreatefrompng($path);
				break;

			case IMAGETYPE_GIF:
				$this->sourceImage = imagecreatefromgif($path);
				break;
		}

		if ($this->sourceImage)
		{
			// Throw error
		}
	}

	private function createProportionalCopy($width, $height)
	{
		$copy = imagecreatetruecolor($width, $height);

		if ($this->sourceType === IMAGETYPE_PNG) {
			$transparency = imagecolorallocatealpha($copy, 0, 0, 0, 127);
			imagefill($copy, 0, 0, $transparency);
		}

		imagecopyresampled($copy, $this->sourceImage, 0, 0, 0, 0, $width, $height, $this->sourceWidth, $this->sourceHeight);

		imagedestroy($this->sourceImage);

		return $copy;
	}
}