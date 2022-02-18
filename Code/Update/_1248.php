<?php

namespace Code\Update;

class _1248
{

    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE atoken ADD atoken_guid NOT NULL DEFAULT '' ");
            $r2 = q("create index \"atoken_guid\" on xchan (\"atoken_guid\")");

            $r = ($r1 && $r2);
        } else {
            $r = q("ALTER TABLE `atoken` ADD `atoken_guid` char(191) NOT NULL DEFAULT '' , 
				ADD INDEX `atoken_guid` (`atoken_guid`)");
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

        $columns = db_columns('atoken');

        if (in_array('atoken_guid', $columns)) {
            return true;
        }

        return false;
    }
}
