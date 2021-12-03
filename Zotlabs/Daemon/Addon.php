<?php

namespace Zotlabs\Daemon;

class Addon
{

    public static function run($argc, $argv)
    {

        call_hooks('daemon_addon', $argv);
    }
}
