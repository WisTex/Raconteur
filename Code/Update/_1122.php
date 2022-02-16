<?php

namespace Code\Update;

class _1122
{
    public function run()
    {
        $r = q(
            "update site set site_realm = '%s' where true",
            dbesc(DIRECTORY_REALM)
        );
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
