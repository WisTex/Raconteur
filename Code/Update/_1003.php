<?php

namespace Code\Update;

class _1003
{
    public function run()
    {
        $r = q("ALTER TABLE `xchan` ADD `xchan_flags` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `xchan_network` ,
ADD INDEX ( `xchan_flags` ) ");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
