<?php

$s = new Setting;
$s->where('name', 'email_delivery_address')->get();

if (!$s->exists())
{
    $user = new User;
    $user->get();

    $email = new Setting;
    $email->name = 'email_delivery_address';
    $email->value = $user->email;
    $email->save();
}

$done = true;
