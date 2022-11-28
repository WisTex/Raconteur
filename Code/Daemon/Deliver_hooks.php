<?php

namespace Code\Daemon;

use Code\Extend\Hook;

class Deliver_hooks implements DaemonInterface
{

    public function run(int $argc, array $argv): void
    {

        if ($argc < 2) {
            return;
        }


        $r = q(
            "select * from item where id = '%d'",
            intval($argv[1])
        );
        if ($r) {
            Hook::call('notifier_normal', $r[0]);
        }
    }
}
