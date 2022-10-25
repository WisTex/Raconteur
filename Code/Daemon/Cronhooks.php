<?php

/** @file */

namespace Code\Daemon;
use Code\Extend\Hook;

class Cronhooks
{

    public function run($argc, $argv)
    {

        logger('cronhooks: start');

        $d = datetime_convert();

        Hook::call('cron', $d);

        return;
    }
}
