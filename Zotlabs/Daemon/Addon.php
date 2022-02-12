<?php

namespace Zotlabs\Daemon;

use Zotlabs\Extend\Hook;

class Addon
{

    public static function run($argc, $argv)
    {

        Hook::call('daemon_addon', $argv);
    }
}
