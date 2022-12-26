<?php

namespace Code\Update;

class _1264
{
    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r = q("ALTER TABLE item ADD approved text NOT NULL DEFAULT ''");
        } else {
            $r = q("ALTER TABLE item ADD approved varchar(255) NOT NULL DEFAULT ''");
        }

        if ($r) {
            q("COMMIT");
            return UPDATE_SUCCESS;
        }

        q("ROLLBACK");
        return UPDATE_FAILED;
    }

    public function verify()
    {
        $columns = db_columns('item');
        return in_array('approved', $columns);
    }
}

