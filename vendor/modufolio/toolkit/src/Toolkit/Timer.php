<?php

namespace Modufolio\Toolkit;

class Timer
{
    public static function app($decimals = 2): string
    {
        $time = microtime(true) - START_TIMER;
        return number_format($time * 1000, $decimals);
    }

    public static function getExecutionTime($decimals = 2): string
    {
        $time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
        return number_format($time * 1000, $decimals);
    }
}