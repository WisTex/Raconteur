<?php

namespace Code\Update;

class _1230
{

    public function run()
    {

        $r1 = q("ALTER TABLE " . TQUOT . 'xchan' . TQUOT . " DROP COLUMN xchan_instance_url ");
        $r2 = q("ALTER TABLE " . TQUOT . 'xchan' . TQUOT . " DROP COLUMN xchan_flags ");

        if ($r1 && $r2) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
