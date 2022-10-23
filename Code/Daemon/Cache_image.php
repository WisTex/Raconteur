<?php

namespace Code\Daemon;

use Code\Lib\Img_cache;

class Cache_image
{

    /**
     * @param $argc
     * @param $argv
     * @return void
     */
    public function run($argc, $argv): void
    {
        cli_startup();
        logger('caching: ' . $argv[1] . ' to ' . $argv[2]);
        if ($argc === 3) {
            Img_cache::url_to_cache($argv[1], $argv[2]);
        }
    }
}
