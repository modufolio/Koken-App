<?php

abstract class Darkroom {

	protected $sourcePath = false;
	protected $sourceWidth = false;
	protected $sourceHeight = false;
	protected $sourceAspect;

	protected $alternatePath = false;
	protected $alternateWidth = false;
	protected $alternateHeight = false;

	protected $isAnimatedGif = false;

	protected $path = false;
	protected $aspect;
	protected $width;
	protected $height;
	protected $bestfit = true;

	protected $retina = false;
	protected $sharpening = false;
	protected $crop = false;
	protected $quality = 85;
	protected $focalX = 0.5;
	protected $focalY = 0.5;
	protected $stripMetadata = false;

	private $beforeRender = false;
	private $beforeRenderExtraArgs = null;

	abstract protected function createImage();
	abstract protected function createCroppedImage($interstitialWidth, $interstitialHeight, $cropX, $cropY);
	abstract protected function rotate($path, $degrees);

	/**
	*
	* Public API
	*
	**/

	public function beforeRender($func, $extraArg = null) {
		$this->beforeRender = $func;
		$this->beforeRenderExtraArg = $extraArg;
		return $this;
	}

	public function render($path = false)
	{
		$this->path = $path;
		$this->aspect = $this->height > 0 ? $this->width / $this->height : 0;

		$this->adjustForRetina();

		$this->bestfit = $this->aspect !== 0;

		$method = 'askForImage';

		if ($this->crop) {
			$method = 'askForCroppedImage';
		}

		$data = $this->$method();

		if ($path) {
			return $this;
		} else {
			return $data;
		}
	}

	public function focus($x, $y)
	{
		$this->focalX = $x / 100;
		$this->focalY = $y / 100;
		return $this;
	}

	public function quality($value)
	{
		$this->quality = $value;
		return $this;
	}

	public function retina()
	{
		$this->retina = true;
		return $this;
	}

	public function alternate($path, $width = false, $height = false)
	{
		$this->alternatePath = $path;

		if ($width && $height) {
			$this->alternateWidth = $width;
			$this->alternateHeight = $height;
		} else {
			list($this->alternateWidth, $this->alternateHeight) = getimagesize($this->alternatePath);
		}

		if (round($this->sourceAspect, 2) !== round($this->alternateWidth / $this->alternateHeight, 2))
		{
			$this->alternatePath = false;
		}
	}

	public function read($path, $width = false, $height = false)
	{
		$this->sourcePath = $path;

		$this->isAnimatedGif = $this->detectAnimatedGif();

		if ($width && $height) {
			$this->sourceWidth = $width;
			$this->sourceHeight = $height;
		} else {
			list($this->sourceWidth, $this->sourceHeight) = getimagesize($this->sourcePath);
		}

		$this->sourceAspect = $this->sourceWidth / $this->sourceHeight;

		return $this;
	}

	public function sharpen($value)
	{
		$this->sharpening = min(1, $value);
		return $this;
	}

	public function strip()
	{
		$this->stripMetadata = true;
	}

	public function resize($width, $height = false, $crop = false)
	{
		$this->width = $width;

		if (is_bool($height)) {
			$this->crop = $height;
			$this->height = $this->width;
		} else {
			$this->height = $height;
			$this->crop = $crop;
		}

		if ($crop) {
			// If cropped and the source is smaller than target,
			// recalc values so no upsizing occurs.
			$aspect = $this->width / $this->height;

			if ($this->width > $this->sourceWidth) {
				$this->width = $this->sourceWidth;
				$this->height = $this->width * $aspect;
			}

			if ($this->height > $this->sourceHeight) {
				$this->height = $this->sourceHeight;
				$this->width = $this->height / $aspect;
			}
		} else {
			$this->width = min($this->width, $this->sourceWidth);
			$this->height = min($this->height, $this->sourceHeight);
		}

		return $this;
	}

	/**
	*
	* Private / protected methods
	*
	**/

	protected function emitBeforeRender($arg)
	{
		if ($this->beforeRender) {
			return call_user_func_array($this->beforeRender, array($arg, array(
				'width' => $this->width,
				'height' => $this->height,
				'retina' => $this->retina,
			), $this->beforeRenderExtraArg));
		}

		return $arg;

	}
	// http://stackoverflow.com/a/415942
	protected function detectAnimatedGif() {
		if ($this->getMimeType() !== 'image/gif') return false;

		if ( ! $fh = @fopen($this->sourcePath, 'rb')) return false;

		$count = 0;
		//an animated gif contains multiple "frames", with each frame having a
		//header made up of:
		// * a static 4-byte sequence (\x00\x21\xF9\x04)
		// * 4 variable bytes
		// * a static 2-byte sequence (\x00\x2C)

		// We read through the file til we reach the end of the file, or we've found
		// at least 2 frame headers
		while(!feof($fh) && $count < 2) {
			$chunk = fread($fh, 1024 * 100); //read 100kb at a time
			$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches);
		}

		fclose($fh);
		return $count > 1;
	}

	private function adjustForRetina()
	{
		if ($this->retina) {
			$this->width *= 2;
			$this->height *= 2;
			$this->quality = max(50, $this->quality - ($this->quality* 0.15));

			if ($this->sharpening) {
				$this->sharpening /= 2;
			}
		}
	}

	private function getMimeType()
	{
		$info = pathinfo($this->sourcePath);

		if (substr($info['extension'], 0, 2) === 'jp') {
			return 'image/jpeg';
		}

		return 'image/' . $info['extension'];
	}

	private function askForImage()
	{
		if ($this->width > 0 && $this->sourceAspect >= $this->aspect) {
			$this->height = round(($this->width * $this->sourceHeight) / $this->sourceWidth);
		} else {
			$this->width = round(($this->height * $this->sourceWidth) / $this->sourceHeight);
		}

		$this->checkAlternate($this->width, $this->height);

		return $this->createImage();
	}

	private function checkAlternate($width, $height)
	{
		if ($this->alternatePath && $this->alternateWidth > $width && $this->alternateHeight > $height) {
			$this->sourcePath = $this->alternatePath;
			$this->sourceWidth = $this->alternateWidth;
			$this->sourceHeight = $this->alternateHeight;
		}
	}

	private function askForCroppedImage()
	{
		if ($this->sourceAspect >= $this->aspect) {
			$interstitialHeight = $this->height;
			$interstitialWidth = ($this->sourceWidth * $this->height) / $this->sourceHeight;
		} else {
			$interstitialWidth = $this->width;
			$interstitialHeight = ($this->sourceHeight * $this->width) / $this->sourceWidth;
		}

		$cropX = max(0, ($interstitialWidth * $this->focalX) - ($this->width / 2));
		$cropY = max(0, ($interstitialHeight * $this->focalY) - ($this->height / 2));

		$cropX = min($interstitialWidth - $this->width, $cropX);
		$cropY = min($interstitialHeight - $this->height, $cropY);

		$this->checkAlternate($interstitialWidth, $interstitialHeight);

		return $this->createCroppedImage($interstitialWidth, $interstitialHeight, $cropX, $cropY);
	}
}