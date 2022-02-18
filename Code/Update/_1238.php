<?php

namespace Code\Update;

class _1238
{

    public function run()
    {

        $r1 = q("CREATE TABLE " . TQUOT . 'block' . TQUOT . " (
		  block_id int(10) UNSIGNED NOT NULL,
		  block_channel_id int(10) UNSIGNED NOT NULL,
		  block_entity text NOT NULL,
		  block_type int(11) NOT NULL,
		  block_comment mediumtext NOT NULL) ");
        $r2 = q("ALTER TABLE " . TQUOT . 'block' . TQUOT . "
		  ADD PRIMARY KEY (block_id),
		  ADD KEY block_channel_id (block_channel_id),
		  ADD KEY block_entity (block_entity(191)),
		  ADD KEY block_type (block_type) ");
        $r3 = q("ALTER TABLE " . TQUOT . 'block' . TQUOT . "
		  MODIFY block_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT ");

        return (($r1 && $r2 && $r3) ? UPDATE_SUCCESS : UPDATE_FAILED);
    }


    public function verify()
    {

        $columns = db_columns('block');

        if (in_array('block_id', $columns)) {
            return true;
        }

        return false;
    }
}
