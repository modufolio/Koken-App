<?php

class GPS
{
    public function __construct(private $exif)
    {
    }

    public function latitude()
    {
        return $this->convert($this->exif['GPSLatitude'], $this->exif['GPSLatitudeRef']);
    }

    public function longitude()
    {
        return $this->convert($this->exif['GPSLongitude'], $this->exif['GPSLongitudeRef']);
    }

    private function convert($arr, $quadrant)
    {
        $d = $this->divide($arr[0]);
        $m = $this->divide($arr[1]);
        $s = $this->divide($arr[2]);
        $dec = ((($s/60)+$m)/60) + $d;
        if (strtolower((string) $quadrant) == 's' || strtolower((string) $quadrant) == 'w') {
            $dec = -$dec;
        }
        return $dec;
    }

    private function divide($str)
    {
        $bits = explode('/', (string) $str);
        $dec = $bits[0] / $bits[1];
        return $dec;
    }
}
