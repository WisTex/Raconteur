<?php

namespace Code\Update;

class _1232
{

    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE photo ADD expires timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
            $r2 = q("create index \"photo_expires_idx\" on photo (\"expires\")");

            $r = ($r1 && $r2);
        } else {
            $r = q("ALTER TABLE `photo` ADD `expires` datetime NOT NULL DEFAULT '0001-01-01 00:00:00' , 
				ADD INDEX `expires` (`expires`)");
        }

        if ($r) {
            q("COMMIT");
            return UPDATE_SUCCESS;
        }

        q("ROLLBACK");
        return UPDATE_FAILED;
    }
}
