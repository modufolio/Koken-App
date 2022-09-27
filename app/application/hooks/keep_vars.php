<?php

// Hack to get original essay content without XSS filters
function keep_vars($vars = array())
{
    if (empty($vars)) {
        return;
    }

    global $raw_input_data;

    $raw_input_data = [];

    foreach ($vars as $var) {
        $raw_input_data = array_merge($raw_input_data, $var);
    }

    foreach ($raw_input_data as &$value) {
        $value = stripslashes($value);
    }
}
