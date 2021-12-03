<?php

namespace Zotlabs\Update;

class _1234
{

    public function run()
    {

        q("START TRANSACTION");

        $r = q("ALTER TABLE oauth_clients ADD client_name VARCHAR(80) ");

        if ($r) {
            q("COMMIT");
            return UPDATE_SUCCESS;
        }

        q("ROLLBACK");
        return UPDATE_FAILED;
    }

    public function verify()
    {

        $columns = db_columns('oauth_clients');

        if (in_array('client_name', $columns)) {
            return true;
        }

        return false;
    }
}
