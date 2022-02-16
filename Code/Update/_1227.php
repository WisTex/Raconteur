<?php

namespace Code\Update;

class _1227
{

    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE item ADD uuid text NOT NULL DEFAULT '' ");
            $r2 = q("create index \"uuid_idx\" on item (\"uuid\")");

            $r = ($r1 && $r2);
        } else {
            $r = q("ALTER TABLE `item` ADD `uuid` char(191) NOT NULL DEFAULT '' , 
				ADD INDEX `uuid` (`uuid`)");
        }

        if ($r) {
            q("COMMIT");
            return UPDATE_SUCCESS;
        }

        q("ROLLBACK");
        return UPDATE_FAILED;
    }
}
