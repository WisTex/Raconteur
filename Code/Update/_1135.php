<?php

namespace Code\Update;

class _1135
{
    public function run()
    {
        $r = q("ALTER TABLE xlink ADD xlink_sig TEXT NOT NULL DEFAULT ''");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
