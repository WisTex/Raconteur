<?php

namespace Zotlabs\Update;

class _1244
{

    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE xchan ADD xchan_created timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
            $r2 = q("create index \"xchan_created_idx\" on xchan (\"xchan_created\")");

            $r = ($r1 && $r2);
        } else {
            $r = q("ALTER TABLE `xchan` ADD `xchan_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00' , 
				ADD INDEX `xchan_created` (`xchan_created`)");
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

        $columns = db_columns('xchan');

        if (in_array('xchan_created', $columns)) {
            return true;
        }

        return false;
    }
}
