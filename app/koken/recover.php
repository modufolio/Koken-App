<?php

    function delete_files($path, $del_dir = false, $level = 0)
    {
        // Trim the trailing slash
        $path = rtrim((string) $path, DIRECTORY_SEPARATOR);

        if (! $current_dir = @opendir($path)) {
            return false;
        }

        while (false !== ($filename = @readdir($current_dir))) {
            if ($filename != "." and $filename != "..") {
                if (is_dir($path.DIRECTORY_SEPARATOR.$filename)) {
                    // Ignore empty folders
                    if (!str_starts_with($filename, '.')) {
                        delete_files($path.DIRECTORY_SEPARATOR.$filename, $del_dir, $level + 1);
                    }
                } else {
                    unlink($path.DIRECTORY_SEPARATOR.$filename);
                }
            }
        }
        @closedir($current_dir);

        if ($del_dir == true and $level > 0) {
            return @rmdir($path);
        }

        return true;
    }

    $movers = ['admin', 'app', 'api.php', 'i.php', 'index.php', 'preview.php', 'dl.php', 'a.php'];

    foreach ($movers as $m) {
        $to = __DIR__ . '/' . $m;
        $path = $to . '.off';

        if (file_exists($path)) {
            if (file_exists($to)) {
                delete_files($to, true, 1);
            }

            rename($path, $to);
        }
    }

    unlink(__FILE__);
