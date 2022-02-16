<?php

$albums = new Album;
$albums->where('listed', 0)->get();
$albums->update_all('visibility', 1);

$done = true;