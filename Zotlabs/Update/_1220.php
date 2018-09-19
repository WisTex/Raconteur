<?php

namespace Zotlabs\Update;

class _1220 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = q("CREATE TABLE listeners (
  id serial NOT NULL,
  target_id text NOT NULL,
  portable_id text NOT NULL,
  ltype smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
)");

			$r2 = q("create index \"target_id_idx\" on listeners (\"target_id\")");
			$r3 = q("create index \"portable_id_idx\" on listeners (\"portable_id\")");
			$r4 = q("create index \"ltype_idx\" on listeners (\"ltype\")");

			$r = $r1 && $r2 && $r3 && $r4;

		}

		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
			$r = q("CREATE TABLE IF NOT EXISTS listeners (
  id int(11) NOT NULL AUTO_INCREMENT,
  target_id varchar(191) NOT NULL DEFAULT '',
  portable_id varchar(191) NOT NULL DEFAULT '',
  ltype int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY target_id (target_id),
  KEY portable_id (portable_id),
  KEY ltype (ltype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		}

		if($r) {
			return UPDATE_SUCCESS;
		}
		return UPDATE_FAILED;

	}

}
