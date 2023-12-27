<?php

use Koken\Toolkit\Dir;
use Koken\Toolkit\F;

class Theme
{
    public function __get($key)
    {
        $CI =& get_instance();
        return $CI->$key;
    }

    public function defaultInfo(array $info, string $theme, string $preview): array
    {
        return [
            'name' => $info['name'] ?? '',
            'version' => $info['version'] ?? '',
            'description' => $info['description'] ?? '',
            'demo' => $info['description'] ?? false,
            'documentation' => $info['documentation'] ?? false,
            'path' => $theme,
            'preview' => $preview,
            'preview_aspect' => 1.558,
            'author' => $info['author'] ?? false,
        ];
    }

    public function read($keys = false)
    {
        $themesDir = BASE_DIR . '/storage/themes';
        $dir = Dir::dirs($themesDir);

        $data = [];
        foreach ($dir as $theme) {


            $info = F::read($themesDir . '/' . $theme . '/info.json');
            if (!isset($info)) {
                continue;
            }

            $info = json_decode($info, true);
            $info = is_array($info) ? $info : [];
            $preview = '/storage/themes/' . $theme . '/preview.jpg';

            $keys === true ? $data[$theme] = $this->defaultInfo($info, $theme, $preview) : $data[] = $this->defaultInfo($info, $theme, $preview);

        }

        return $data;
    }

}
