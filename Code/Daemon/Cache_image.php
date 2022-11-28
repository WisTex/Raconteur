<?php

namespace Code\Daemon;

use Code\Lib\Img_cache;

class Cache_image implements DaemonInterface
{

    /**
     * @param int $argc
     * @param array $argv
     * @return void
     */
    public function run(int $argc, array $argv): void
    {
        cli_startup();
        logger('caching: ' . $argv[1] . ' to ' . $argv[2]);
        if ($argc === 3) {
            Img_cache::url_to_cache($argv[1], $argv[2]);
        }
    }
}
