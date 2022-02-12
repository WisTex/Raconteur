<?php

/** @file */

namespace Zotlabs\Daemon;
use Zotlabs\Extend\Hook;

class Cronhooks
{

    public static function run($argc, $argv)
    {

        logger('cronhooks: start');

        $d = datetime_convert();

        Hook::call('cron', $d);

        return;
    }
}
