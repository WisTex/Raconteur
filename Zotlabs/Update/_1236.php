<?php

namespace Zotlabs\Update;

class _1236
{

    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE abook ADD abook_alias text NOT NULL");
            $r2 = q("create index \"abook_alias_idx\" on photo (\"abook_alias\")");

            $r = ($r1 && $r2);
        } else {
            $r = q("ALTER TABLE `abook` ADD `abook_alias` char(191) NOT NULL DEFAULT '' , 
				ADD INDEX `abook_alias` (`abook_alias`)");
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

        $columns = db_columns('abook');

        if (in_array('abook_alias', $columns)) {
            return true;
        }

        return false;
    }
}
