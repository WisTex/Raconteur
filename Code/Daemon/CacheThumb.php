<?php

/** @file */

namespace Code\Daemon;
use Code\Lib\Resizer;

require_once('include/photos.php');

class CacheThumb implements DaemonInterface
{
    public function run(int $argc, array $argv): void
    {
        if ($argc != 2) {
            return;
        }

        $path = 'cache/img/' . substr($argv[1], 0, 2) . '/' . $argv[1];
        $imagesize = getimagesize($path);

        if (! $imagesize) {
            return;
        }

        $max_thumb = get_config('system', 'max_cache_thumbnail', 1024);
        $resizer = new Resizer(get_config('system','imagick_convert_path'), $imagesize);
        $resized = $resizer->resize(PROJECT_BASE . '/' . $path, PROJECT_BASE . '/' . $path . '-001', $max_thumb);
        if ($resized) {
            @rename($path . '-001', $path);
        }
    }
}
