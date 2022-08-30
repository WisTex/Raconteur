<?php

namespace Code\Update;

class _1260
{

    public function run()
    {

        $default = ((ACTIVE_DBTYPE == DBTYPE_POSTGRES) ? " default ''" : '');

        q("START TRANSACTION");
        $r = q("ALTER TABLE outq ADD outq_log text NOT NULL $default");

        if ($r) {
            q("COMMIT");
            return UPDATE_SUCCESS;
        }

        q("ROLLBACK");
        return UPDATE_FAILED;
    }

    public function verify()
    {

        $columns = db_columns('outq');

        if (in_array('outq_log', $columns)) {
            return true;
        }

        return false;
    }
}
