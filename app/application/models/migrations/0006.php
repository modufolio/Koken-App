<?php

    $user = new User();

    $user->update(['public_first_name' => 'first_name', 'public_last_name' => 'last_name', 'public_email' => 'email'], false);

    $done = true;
