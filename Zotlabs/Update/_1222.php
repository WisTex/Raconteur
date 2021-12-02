<?php

namespace Zotlabs\Update;

class _1222
{

    public function run()
    {

        $r = dbq("UPDATE hubloc set hubloc_id_url = hubloc_hash where hubloc_id_url = '' ");

        if ($r) {
            return UPDATE_SUCCESS;
        } else {
            return UPDATE_FAILED;
        }
    }

}
