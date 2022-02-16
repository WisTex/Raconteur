<?php

namespace Code\Update;

class _1192
{
    public function run()
    {

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("CREATE INDEX item_obj_type ON item (obj_type)");
        } else {
            $r1 = q("ALTER TABLE item ADD INDEX (obj_type)");
        }

        if ($r1) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
