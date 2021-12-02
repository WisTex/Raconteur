<?php

namespace Zotlabs\Update;

class _1229
{

    public function run()
    {

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r = q("ALTER TABLE " . TQUOT . 'xchan' . TQUOT . " RENAME COLUMN xchan_pubforum to xchan_type ");
        } else {
            $r = q("ALTER TABLE " . TQUOT . 'xchan' . TQUOT . " CHANGE xchan_pubforum xchan_type tinyint(1) NOT NULL default 0 ");
        }

        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;

    }

}
