<?php

namespace Code\Update;

class _1064
{
    public function run()
    {
        $r = q("ALTER TABLE `updates` ADD `ud_guid` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `ud_hash` ,
ADD INDEX ( `ud_guid` )");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
