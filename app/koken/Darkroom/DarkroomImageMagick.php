<?php

class DarkroomImageMagick extends Darkroom
{
    private $sourceArgs = [];
    private $destinationArgs = [];
    private $limits = [];
    private $isGmagick = false;

    public function __construct(private $pathToConvert = 'convert', $limits = [])
    {
        $this->isGmagick = str_contains((string) $this->pathToConvert, 'gm convert');

        $this->limits = array_merge(['thread' => false, 'memory' => false, 'map' => false], $limits);

        if ($this->limits['thread'] !== false) {
            /*
                Thread limit is supported in GraphicsMagick, but is a newer addition. It
                also uses "-limit threads" instead of ImageMagick's "-limit thread". Would
                need a way to detect this, so just turn it off for now.

                http://www.graphicsmagick.org/OpenMP.html
            */
            if ($this->isGmagick) {
                $this->limits['thread'] = false;
            } else {
                $cache = __DIR__.'/thread';

                if (file_exists($cache)) {
                    if (trim(file_get_contents($cache)) !== 'on') {
                        $this->limits['thread'] = false;
                    }
                } else {
                    // Also not supported if it isn't listed in the idenfify output
                    // (not compiled with OpenMP)
                    $resourceOutput = shell_exec(str_replace('convert', 'identify -list resource', $this->pathToConvert));
                    if (!str_contains(strtolower($resourceOutput), 'thread')) {
                        $this->limits['thread'] = false;
                    }

                    file_put_contents($cache, $this->limits['thread'] === false ? 'off' : 'on');
                }
            }
        }
    }

    private function setupArgs()
    {
        $this->sourceArgs = [$this->pathToConvert];
        $this->destinationArgs = array_merge(
            $this->setupLimitArgs(),
            ['-density 72', "-quality {$this->quality}"]
        );

        if ($this->stripMetadata) {
            $this->sourceArgs[] = '-strip';
        }

        if ($this->isAnimatedGif) {
            if ($this->isGmagick) {
                // GraphicsMagick has inline coalesce issues, so this uses an interstitial in-memory image instead
                // http://sourceforge.net/p/graphicsmagick/mailman/message/32004613/
                array_unshift($this->destinationArgs, '-coalesce miff:- | ' . $this->pathToConvert . ' miff:-');
            } else {
                array_unshift($this->destinationArgs, '-coalesce');
            }
        }
    }

    private function setupLimitArgs()
    {
        $array = [];

        if ($this->limits['thread']) {
            $array[] = '-limit thread ' . $this->limits['thread'];
        }

        if ($this->limits['memory']) {
            $array[] = '-limit memory ' . $this->limits['memory'];
        }

        if ($this->limits['map']) {
            $array[] = '-limit map ' . $this->limits['map'];
        }

        return $array;
    }

    public function getQuality()
    {
        $sourcePath = $this->sourcePath;

        if ($this->isGmagick && str_starts_with((string) $sourcePath, 'https://')) {
            $sourcePath = str_replace('https://', 'http://', $sourcePath);
        }

        if ($this->isGmagick) {
            $cmd = "gm identify -format '%[JPEG-Quality]' ";
        } else {
            $cmd = "identify -format '%Q' ";
        }

        $cmd .= $sourcePath;

        $quality = (int) shell_exec($cmd);
        return $quality;
    }

    #[\Override]
    public function rotate($path, $degrees)
    {
        if ($this->isGmagick && str_starts_with((string) $path, 'https://')) {
            $path = str_replace('https://', 'http://', $path);
        }
        $path = '"' . $path . '"';
        $limits = implode(' ', $this->setupLimitArgs());
        $cmd = "{$this->pathToConvert} $path -rotate $degrees $limits $path";
        shell_exec($cmd);

        return $this;
    }

    #[\Override]
    public function createImage()
    {
        $this->setupArgs();

        $this->sourceArgs[] = "-size {$this->width}x{$this->height}";
        $this->destinationArgs[] = "-scale {$this->width}x{$this->height}";

        return $this->output();
    }

    #[\Override]
    public function createCroppedImage($interstitialWidth, $interstitialHeight, $cropX, $cropY)
    {
        $this->setupArgs();

        $this->sourceArgs[] = "-size {$interstitialWidth}x{$interstitialHeight}";

        if ($this->sourceAspect >= $this->aspect) {
            $resizeString = 'x' . $this->height;
        } else {
            $resizeString = $this->width . 'x';
        }

        $this->destinationArgs[] = "-scale $resizeString";
        $this->destinationArgs[] = "-crop {$this->width}x{$this->height}+{$cropX}+{$cropY}";
        $this->destinationArgs[] = "-repage {$this->width}x{$this->height}+0+0";

        return $this->output();
    }

    private function output()
    {
        $sourcePath = $this->sourcePath;

        if ($this->isGmagick && str_starts_with((string) $sourcePath, 'https://')) {
            $sourcePath = str_replace('https://', 'http://', $sourcePath);
        }

        $this->sourceArgs[] = '-depth 8';
        $this->sourceArgs[] = '"' . $sourcePath . '"';

        if ($this->sharpening !== false) {
            $sigma = $this->sharpening * 1.3;
            $this->destinationArgs[] = "-unsharp 0x{$sigma}+{$this->sharpening}+0.05";
        }

        if ($this->isAnimatedGif) {
            $this->destinationArgs[] = '-deconstruct';
        }

        $cmd = implode(' ', array_merge(
            $this->sourceArgs,
            $this->destinationArgs
        ));

        $cmd = $this->emitBeforeRender($cmd);

        if ($this->path) {
            $cmd .= ' "' . $this->path . '"';
        } else {
            $cmd .= ' -';
        }

        return shell_exec($cmd);
    }
}
