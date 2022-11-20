<?php

namespace Code\Daemon;

use Code\Extend\Hook;

class Addon
{

    /**
     * @param int $argc
     * @param array $argv
     * @return void
     * @noinspection PhpUnusedParameterInspection
     */
    public function run(int $argc, array $argv): void
    {
        Hook::call('daemon_addon', $argv);
    }
}
