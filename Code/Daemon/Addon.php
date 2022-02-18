<?php

namespace Code\Daemon;

use Code\Extend\Hook;

class Addon
{

    public static function run($argc, $argv)
    {

        Hook::call('daemon_addon', $argv);
    }
}
