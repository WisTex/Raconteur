<?php

namespace Code\Update;

class _1104
{
    public function run()
    {
        $r = q("ALTER TABLE `item` ADD `route` TEXT NOT NULL DEFAULT '' AFTER `postopts` ");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
