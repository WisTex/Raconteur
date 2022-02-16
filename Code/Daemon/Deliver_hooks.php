<?php

namespace Code\Daemon;

use Code\Extend\Hook;

class Deliver_hooks
{

    public static function run($argc, $argv)
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
