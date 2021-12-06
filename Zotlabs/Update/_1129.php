<?php

namespace Zotlabs\Update;

class _1129
{
    public function run()
    {
        $r = q("update hubloc set hubloc_network = 'zot' where hubloc_network = ''");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
