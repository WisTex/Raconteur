<?php

namespace Zotlabs\Update;

class _1241
{

    public function run()
    {
        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE pgrp ADD \"rule\" text NOT NULL DEFAULT '' ");
            $r2 = q("create index \"group_rule_idx\" on pgrp (\"rule\")");

            $r = ($r1 && $r2);
        } else {
            $r = q("ALTER TABLE `pgrp` ADD `rule` char(191) NOT NULL DEFAULT '' , 
				ADD INDEX `rule` (`rule`)");
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

        $columns = db_columns('pgrp');

        if (in_array('rule', $columns)) {
            return true;
        }

        return false;
    }


}
