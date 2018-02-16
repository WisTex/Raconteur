<?php

namespace Zotlabs\Update;

class _1196 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("CREATE TABLE \"pchan\" (
  \"pchan_id\" serial NOT NULL,
  \"pchan_guid\" text NOT NULL,
  \"pchan_hash\" text NOT NULL,
  \"pchan_pubkey\" text NOT NULL,
  \"pchan_prvkey\" text NOT NULL,
  PRIMARY KEY (\"pchan_id\")
)");

		$r2 = q("create index \"pchan_guid\" on pchan (\"pchan_guid\")");
		$r3 = q("create index \"pchan_hash\" on pchan (\"pchan_hash\")");

		if($r1 && $r2 && $r3) {
			return UPDATE_SUCCESS;
		}
	}
	else {
		$r1 = q("CREATE TABLE IF NOT EXISTS pchan (
  pchan_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  pchan_guid char(191) NOT NULL DEFAULT '',
  pchan_hash char(191) NOT NULL DEFAULT '',
  pchan_pubkey text NOT NULL,
  pchan_prvkey text NOT NULL,
  PRIMARY KEY (pchan_id),
  KEY pchan_guid (pchan_guid),
  KEY pchan_hash (pchan_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		if($r1) {
			return UPDATE_SUCCESS;
		}
	}

	return UPDATE_FAILED;
}


}