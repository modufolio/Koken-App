<?php

namespace Modufolio\Toolkit;

class Grep
{

    /**
     * Return array entries that contains the needle
     * @param string $needle
     * @param array $haystack
     * @return array|false
     */
    public static function contains(string $needle, array $haystack)
    {
        return preg_grep("/$needle/i", $haystack);
    }

    /**
     * Return array entries that contains the needle
     * @param string $needle
     * @param array $haystack
     * @return array|false
     */
    public static function startsWith(string $needle, array $haystack)
    {
        return preg_grep("/^$needle/i", $haystack);
    }

    public static function endsWith(string $needle, array $haystack)
    {
        return preg_grep("/$needle$/i", $haystack);
    }

    /**
     * Return array entries that have the same soundex value
     * @param string $needle
     * @param array $haystack
     * @return array|false
     */
    public static function soundex(string $needle, array $haystack): array
    {
        return array_filter($haystack, function ($item) use ($needle) {
            return soundex($item) === soundex($needle);
        });
    }

}