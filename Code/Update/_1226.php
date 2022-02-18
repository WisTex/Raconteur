<?php

namespace Code\Update;

class _1226
{

    public function run()
    {

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r = q("alter table item add item_level bigint NOT NULL DEFAULT '0'");
        } else {
            $r = q("alter table item add item_level int(10) NOT NULL DEFAULT 0 ");
        }

        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
