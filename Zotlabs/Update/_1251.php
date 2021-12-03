<?php

namespace Zotlabs\Update;

class _1251
{

    public function run()
    {

        $default = ((ACTIVE_DBTYPE == DBTYPE_POSTGRES) ? " default ''" : '');

        q("START TRANSACTION");
        $r = q("ALTER TABLE dreport ADD dreport_log text NOT NULL $default");

        if ($r) {
            q("COMMIT");
            return UPDATE_SUCCESS;
        }

        q("ROLLBACK");
        return UPDATE_FAILED;
    }

    public function verify()
    {

        $columns = db_columns('dreport');

        if (in_array('dreport_log', $columns)) {
            return true;
        }

        return false;
    }
}
