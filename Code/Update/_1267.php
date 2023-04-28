<?php

namespace Code\Update;

class _1267
{

        public function run()
        {

        if (ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
            $r1 = q("CREATE TABLE tombstone (
                id serial NOT NULL,
                id_hash varchar(255) NOT NULL,
                id_channel bigint NOT NULL,
                deleted_at timestamp NOT NULL DEFAUL '0001-01-01 00:00:00',
                PRIMARY KEY (id)"
            );

            $r2 = q("create index \"id_hash\" on tombstone (\"id_hash\")");
            $r3 = q("create index \"id_channel\" on tombstone (\"id_channel\")");
            $r4 = q("create index \"deleted_at\" on tombstone (\"deleted_at\")");

            $r = $r1 && $r2 && $r3 && $r4;
        }

        if (ACTIVE_DBTYPE == DBTYPE_MYSQL) {
            $r = q("CREATE TABLE IF NOT EXISTS `tombstone` (
                `id` int NOT NULL AUTO_INCREMENT,
                `id_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
                `id_channel` int NOT NULL,
                `deleted_at` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
                PRIMARY KEY (`id`),
                KEY `id_hash` (`id_hash`(191)),
                KEY `id_channel` (`id_channel`),
                KEY `deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }


    public function verify()
    {

        $columns = db_columns('tombstone');

        if (in_array('id', $columns)) {
            return true;
        }

        return false;
    }


}

