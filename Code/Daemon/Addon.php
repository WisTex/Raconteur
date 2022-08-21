<?php

namespace Code\Daemon;

use Code\Extend\Hook;

class Addon
{

    /**
     * @param $argc
     * @param $argv
     * @return void
     */
    public static function run($argc, $argv): void
    {
        Hook::call('daemon_addon', $argv);
    }
}
