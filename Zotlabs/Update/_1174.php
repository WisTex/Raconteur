<?php

namespace Zotlabs\Update;

class _1174 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("ALTER TABLE event CHANGE `type` `etype` varchar(255) NOT NULL DEFAULT '' ");
		$r2 = q("ALTER TABLE event CHANGE `start` `dtstart` timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
		$r3 = q("ALTER TABLE event CHANGE `finish` `dtend` timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
		$r4 = q("ALTER TABLE event CHANGE `ignore` `dismissed` numeric(1) NOT NULL DEFAULT '0' ");
		$r5 = q("ALTER TABLE attach CHANGE `data` `content` bytea NOT NULL ");
		$r6 = q("ALTER TABLE photo CHANGE `data` `content` bytea NOT NULL ");
	}
	else {
		$r1 = q("ALTER TABLE event CHANGE `type` `etype` char(255) NOT NULL DEFAULT '' ");
		$r2 = q("ALTER TABLE event CHANGE `start` `dtstart` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' ");
		$r3 = q("ALTER TABLE event CHANGE `finish` `dtend` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' ");
		$r4 = q("ALTER TABLE event CHANGE `ignore` `dismissed` tinyint(1) NOT NULL DEFAULT '0' ");
		$r5 = q("ALTER TABLE attach CHANGE `data` `content` longblob NOT NULL ");
		$r6 = q("ALTER TABLE photo CHANGE `data` `content` mediumblob NOT NULL ");
	}

	if($r1 && $r2 && $r3 && $r4 && $r5 && $r6)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}