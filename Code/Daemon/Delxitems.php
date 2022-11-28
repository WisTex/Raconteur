<?php

namespace Code\Daemon;

/*
 * Daemon to remove 'item' resources in the background from a removed connection
 */

class Delxitems implements DaemonInterface
{

    public function run(int $argc, array $argv): void
    {

        cli_startup();

        if ($argc != 3) {
            return;
        }

        remove_abook_items($argv[1], $argv[2]);
    }
}
