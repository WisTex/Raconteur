<?php

namespace Code\Update;

class _1166
{
    public function run()
    {

        $r = q("alter table source add src_tag text not null default '' ");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
