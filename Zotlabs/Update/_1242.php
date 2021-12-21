<?php

namespace Zotlabs\Update;

class _1242
{

    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE xchan ADD xchan_updated timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
            $r2 = q("create index \"xchan_updated_idx\" on xchan (\"xchan_updated\")");

            $r = ($r1 && $r2);
        } else {
            $r = q("ALTER TABLE `xchan` ADD `xchan_updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00' , 
				ADD INDEX `xchan_updated` (`xchan_updated`)");
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

        if (in_array('xchan_updated', $columns)) {
            return true;
        }

        return false;
    }
}
