<?php

namespace Zotlabs\Update;

class _1111
{
    public function run()
    {
        $r = q("ALTER TABLE `app` ADD `app_requires` CHAR( 255 ) NOT NULL DEFAULT '' ");
        if ($r)
            return UPDATE_SUCCESS;
        return UPDATE_FAILED;
    }


}