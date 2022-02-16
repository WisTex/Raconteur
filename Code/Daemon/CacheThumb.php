<?php

/** @file */

namespace Code\Daemon;

require_once('include/photos.php');

class CacheThumb
{

    public static function run($argc, $argv)
    {

        if (! $argc == 2) {
            return;
        }

        $path = 'cache/img/' . substr($argv[1], 0, 2) . '/' . $argv[1];

        $is = getimagesize($path);

        if (! $is) {
            return;
        }

        $width  = $is[0];
        $height = $is[1];

        $max_thumb = get_config('system', 'max_cache_thumbnail', 1024);

        if ($width > $max_thumb || $height > $max_thumb) {
            $imagick_path = get_config('system', 'imagick_convert_path');
            if ($imagick_path && @file_exists($imagick_path)) {
                $tmp_name = $path . '-001';
                $newsize = photo_calculate_scale(array_merge($is, ['max' => $max_thumb]));
                $cmd = $imagick_path . ' ' . escapeshellarg(PROJECT_BASE . '/' . $path) . ' -resize ' . $newsize . ' ' . escapeshellarg(PROJECT_BASE . '/' . $tmp_name);

                for ($x = 0; $x < 4; $x++) {
                    exec($cmd);
                    if (file_exists($tmp_name)) {
                        break;
                    }
                    continue;
                }

                if (! file_exists($tmp_name)) {
                    return;
                }
                @rename($tmp_name, $path);
            }
        }
    }
}
