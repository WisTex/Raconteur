<?php

/** @file */

namespace Code\Daemon;
use Code\Extend\Hook;

class Cronhooks implements DaemonInterface
{

    public function run(int $argc, array $argv): void
    {

        logger('cronhooks: start');

        $d = datetime_convert();

        Hook::call('cron', $d);
    }
}
