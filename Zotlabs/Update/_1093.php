<?php

namespace Zotlabs\Update;

class _1093
{
    public function run()
    {
        $r = q("ALTER TABLE `chatpresence` ADD `cp_client` CHAR( 128 ) NOT NULL DEFAULT ''");
        if ($r)
            return UPDATE_SUCCESS;
        return UPDATE_FAILED;
    }


}