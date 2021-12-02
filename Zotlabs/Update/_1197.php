<?php

namespace Zotlabs\Update;

class _1197
{
    public function run()
    {

        $r = q("select diaspora_meta from item where true limit 1");
        if ($r) {
            $r = q("ALTER TABLE item DROP diaspora_meta");
        }

        return UPDATE_SUCCESS;
    }


}