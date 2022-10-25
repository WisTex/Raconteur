<?php

namespace Code\Daemon;

use Code\Extend\Hook;

class Addon
{

    /**
     * @param $argc
     * @param $argv
     * @return void
     * @noinspection PhpUnusedParameterInspection
     */
    public function run($argc, $argv): void
    {
        Hook::call('daemon_addon', $argv);
    }
}
