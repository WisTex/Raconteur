<?php

namespace Zotlabs\Update;

class _1145 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 
		$r1 = q("ALTER TABLE event ADD event_status char(255) NOT NULL DEFAULT '', 
			ADD event_status_date timestamp NOT NULL DEFAULT '0001-01-01 00:00:00', 
			ADD event_percent SMALLINT NOT NULL DEFAULT '0', 
			ADD event_repeat TEXT NOT NULL DEFAULT '' ");
		$r2 = q("create index event_status on event ( event_status )");
		$r = $r1 && $r2;
	}
	else {
		$r = q("ALTER TABLE `event` ADD `event_status` CHAR( 255 ) NOT NULL DEFAULT '',
			ADD `event_status_date` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
			ADD `event_percent` SMALLINT NOT NULL DEFAULT '0',
			ADD `event_repeat` TEXT NOT NULL DEFAULT '',
			ADD INDEX ( `event_status` ) ");
 	}
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}