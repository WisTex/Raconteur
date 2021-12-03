<?php

namespace Zotlabs\Update;

class _1243
{

    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE profile ADD pronouns text NOT NULL DEFAULT '' ");
            $r2 = q("ALTER TABLE xprof ADD xprof_pronouns text NOT NULL DEFAULT '' ");

            $r = ($r1 && $r2);
        } else {
            $r1 = q("ALTER TABLE `profile` ADD `pronouns` char(191) NOT NULL DEFAULT '' ");
            $r2 = q("ALTER TABLE `xprof` ADD `xprof_pronouns` char(191) NOT NULL DEFAULT '' ");
            $r = ($r1 && $r2);
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

        $columns = db_columns('profile');
        $columns2 = db_columns('xprof');

        if (in_array('pronouns', $columns) && in_array('xprof_pronouns', $columns2)) {
            return true;
        }

        return false;
    }


}
