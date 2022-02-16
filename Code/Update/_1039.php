<?php

namespace Code\Update;

class _1039
{
    public function run()
    {
        $r = q("ALTER TABLE `channel` CHANGE `channel_default_gid` `channel_default_group` CHAR( 255 ) NOT NULL DEFAULT ''");

        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
