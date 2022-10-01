<?php

namespace Modufolio\Toolkit;

/**
 * A set of date methods
 *
 * @package   Modufolio Toolkit
 * @author    Maarten Thiebou
 * @copyright Modufolio
 * @license   https://opensource.org/licenses/MIT
 */
class Date
{
    public static array $translation = [];

    public static function parse(string $date, $format = null, $lang = null)
    {
        if (isset($format)) {
            return self::convert($date, $format, 'Y-m-d');
        }

        if (strstr($date, '/') !== false) {
            return self::convert($date, 'd/m/Y', 'Y-m-d');
        }

        if (isset($lang) && $lang != 'en') {
            self::translate($date, $lang);  // translate date to english
            return self::format($date);
        }

        return self::format($date); // default format

    }

    public static function translate(string $date, string $lang): string
    {
        if (!in_array($lang, array_keys(self::$translation))) {
            return false;
        }

        $count = count(array_filter(str_split($date), 'ctype_alpha')); // count the number of letters in the date
        $month = $count === 3 ? 'M' : 'F';

        return str_ireplace(self::$translation[$lang][$month], self::$translation['en'][$month], $date);
    }

    public static function format(string $date, $format = 'Y-m-d')
    {
        $dateTime = date_create($date);
        return is_a($dateTime, 'DateTime') ? date_format($dateTime, $format) : false;
    }


    public static function convert(string $date, string $from, string $to)
    {
        $dateTime = date_create_from_format($from, $date);
        return is_a($dateTime, 'DateTime') ? date_format($dateTime, $to) : false;
    }

    public static function getMonthName(int $month, $lang = 'en')
    {
        return self::$translation[$lang]['F'][$month - 1];
    }

    public static function today()
    {
        return date('Y-m-d');
    }

    public static function yesterday()
    {
        return date('Y-m-d', strtotime('-1 day'));
    }

    public static function tomorrow()
    {
        return date('Y-m-d', strtotime('+1 day'));
    }
}

/**
 * Default set of translations for the date class
 */
Date::$translation = [
    'en' => [
        'M' => ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'],
        'F' => ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']
    ],
    'nl' => [
        'M' => ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'],
        'F' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december']
    ],
    'fr' => [
        'M' => ['jan', 'fév', 'mar', 'avr', 'mai', 'jun', 'jui', 'aoû', 'sep', 'oct', 'nov', 'déc'],
        'F' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']
    ],
    'de' => [
        'M' => ['jan', 'feb', 'mär', 'apr', 'mai', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dez'],
        'F' => ['januar', 'februar', 'märz', 'april', 'mai', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'dezember']
    ],
    'es' => [
        'M' => ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
        'F' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
    ],
    'it' => [
        'M' => ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'],
        'F' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre']
    ]
];
