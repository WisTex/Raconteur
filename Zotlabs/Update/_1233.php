<?php

namespace Zotlabs\Update;

class _1233
{

    public function run()
    {

        q("START TRANSACTION");

        $r = q("ALTER TABLE abook ADD abook_censor INT UNSIGNED NOT NULL DEFAULT '0' ");

        if ($r) {
            q("COMMIT");
            return UPDATE_SUCCESS;
        }

        q("ROLLBACK");
        return UPDATE_FAILED;

    }

    public function verify()
    {

        $columns = db_columns('abook');

        if (in_array('abook_censor', $columns)) {
            return true;
        }

        return false;
    }

}
