<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	http://codeigniter.com/user_guide/general/hooks.html
|
*/

$hook['pre_system'] = array(
    'class'    => '',
    'function' => 'keep_vars',
    'filename' => 'keep_vars.php',
    'filepath' => 'hooks',
    'params'   => array($_POST, $_GET)
);

/* End of file hooks.php */
/* Location: ./system/application/config/hooks.php */