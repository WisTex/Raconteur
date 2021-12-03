<?php

namespace Zotlabs\Update;

class _1231
{

    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE channel ADD channel_parent text NOT NULL DEFAULT '' ");
            $r2 = q("create index \"channel_parent\" on channel (\"channel_parent\")");

            $r = ($r1 && $r2);
        } else {
            $r = q("ALTER TABLE `channel` ADD `channel_parent` char(191) NOT NULL DEFAULT '' , 
				ADD INDEX `channel_parent` (`channel_parent`)");
        }

        if ($r) {
            q("COMMIT");
            return UPDATE_SUCCESS;
        }

        q("ROLLBACK");
        return UPDATE_FAILED;
    }
}
