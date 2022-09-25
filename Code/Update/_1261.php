<?php

namespace Code\Update;

class _1261
{
    public function run()
    {

        q("START TRANSACTION");

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("ALTER TABLE item ADD lat float NOT NULL DEFAULT '0'");
            $r2 = q("create index \"lat_idx\" on item (\"lat\")");
            $r3 = q("ALTER TABLE item ADD lon float NOT NULL DEFAULT '0'");
            $r4 = q("create index \"lon_idx\" on item (\"lon\")");

            $r = ($r1 && $r2 && $r3 && $r4);
        } else {
            $r1 = q("ALTER TABLE item ADD lat float NOT NULL DEFAULT '0' , 
				ADD INDEX `lat` (`lat`)");
            $r2 = q("ALTER TABLE item ADD lon float NOT NULL DEFAULT '0' , 
				ADD INDEX `lon` (`lon`)");
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

        $columns = db_columns('item');

        if (in_array('lat', $columns) && in_array('lon', $columns)) {
            return true;
        }
        return false;
    }
}

