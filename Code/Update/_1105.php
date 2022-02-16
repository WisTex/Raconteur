<?php

namespace Code\Update;

class _1105
{
    public function run()
    {
        $r = q("ALTER TABLE `site` ADD `site_pull` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' AFTER `site_update` ,
CHANGE `site_sync` `site_sync` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00', ADD INDEX ( `site_pull` ) ");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
