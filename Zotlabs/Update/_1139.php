<?php

namespace Zotlabs\Update;

class _1139 {
function run() {
	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 
		$r1 = q("ALTER TABLE channel ADD channel_lastpost timestamp NOT NULL DEFAULT '0001-01-01 00:00:00'");
		$r2 = q("create index channel_lastpost on channel ( channel_lastpost ) ");
		$r = $r1 && $r2;
	}
	else
		$r = q("ALTER TABLE `channel` ADD `channel_lastpost` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' AFTER `channel_dirdate` , ADD INDEX ( `channel_lastpost` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}