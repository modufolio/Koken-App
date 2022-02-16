<?php

class DarkroomImagick extends Darkroom {

	private $image;
	private $limits = array();
	private $rotate = false;

	function __construct($limits = array())
	{
		$this->limits = array_merge(array(
			'thread' => false,
			'memory' => false,
			'map' => false,
		), $limits);

		$memory_limit = (int) ini_get('memory_limit');

		if (is_numeric($memory_limit) && $memory_limit < 256)
		{
			ini_set('memory_limit', '256M');
		}
	}

	public function getQuality()
	{
		$image = new Imagick();
		$this->setLimits($image);

		$image->readImage($this->sourcePath);
		$quality = $image->getImageCompressionQuality();

		return $quality;
	}

	public function rotate($path, $degrees)
	{
		$image = new Imagick();
		$this->setLimits($image);

		$image->readImage($path);
		$image->rotateImage( new ImagickPixel(), $degrees );
		$image->writeImage($path);
		$image->destroy();

		return $this;
	}

	private function setLimits($image)
	{
		if ($this->limits['thread']) {
			$image->setResourceLimit(6, $this->limits['thread']);
		}

		if ($this->limits['memory']) {
			$image->setResourceLimit(IMagick::RESOURCETYPE_MEMORY, $this->limits['memory']);
		}

		if ($this->limits['map']) {
			$image->setResourceLimit(IMagick::RESOURCETYPE_MAP, $this->limits['map']);
		}
	}

	private function init($hintWidth, $hintHeight)
	{
		$this->image = new Imagick();

		$this->setLimits($this->image);

		$this->image->setSize($hintWidth, $hintHeight);
		$this->image->readImage($this->sourcePath);

		if ($this->stripMetadata) {
			$this->image->stripImage();
		}

		if ($this->isAnimatedGif) {
			$this->image = $this->image->coalesceImages();
		}

		$this->image->setImageCompressionQuality($this->quality);
		$this->image->setImageResolution(72, 72);
		$this->image->setImageDepth(8);
	}

	private function createImageFrame($image)
	{
		$image->scaleImage($this->width, $this->height, $this->bestfit);
	}

	public function createImage()
	{
		$this->init($this->width, $this->height);

		if ($this->isAnimatedGif) {
			foreach ($this->image as $frame) {
				$this->createImageFrame($frame);
			}
		} else {
			$this->createImageFrame($this->image);
		}

		return $this->output();
	}

	private function createCroppedImageFrame($image, $width, $height, $x, $y)
	{
		$image->scaleImage($width, $height);
		$image->cropImage($this->width, $this->height, $x, $y);
		$image->setImagePage($this->width, $this->height, 0, 0);
	}

	public function createCroppedImage($interstitialWidth, $interstitialHeight, $cropX, $cropY)
	{
		$this->init($interstitialWidth, $interstitialHeight);

		if ($this->isAnimatedGif) {
			foreach ($this->image as $frame) {
				$this->createCroppedImageFrame($frame, $interstitialWidth, $interstitialHeight, $cropX, $cropY);
			}
		} else {
			$this->createCroppedImageFrame($this->image, $interstitialWidth, $interstitialHeight, $cropX, $cropY);
		}

		return $this->output();
	}

	private function output()
	{
		if ($this->sharpening !== false) {
			$sigma = $this->sharpening * 1.3;
			$this->image->unsharpMaskImage( 0, $sigma, $this->sharpening, 0.05 );
		}

		$this->image = $this->emitBeforeRender($this->image);

		if ($this->path) {
			if ($this->isAnimatedGif) {
				$this->image = $this->image->deconstructImages();
				$this->image->writeImages($this->path, true);
			} else {
				$this->image->writeImage($this->path);
			}
			$this->image->destroy();
		} else {
			$method = $this->isAnimatedGif ? 'getImagesBlob' : 'getImageBlob';
			$image = (string) $this->image->$method();
			$this->image->destroy();
			return $image;
		}
	}
}
