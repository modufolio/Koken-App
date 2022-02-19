<?php

$albums = new Album;
$albums->where('sort', '[object Object]')->get();
$albums->update_all('sort', 'manual ASC');

$done = true;