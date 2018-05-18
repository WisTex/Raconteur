<?php

namespace Zotlabs\Update;

class _1211 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = q("ALTER TABLE channel ADD channel_active timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
 			$r2 = q("create index \"channel_active_idx\" on channel (\"channel_active\")");

			$r = ($r1 && $r2);
		}
		else {
			$r = q("ALTER TABLE `channel` ADD `channel_active` datetime NOT NULL DEFAULT '0001-01-01 00:00:00' , 
				ADD INDEX `channel_active` (`channel_active`)");
		}

		if($r)
			return UPDATE_SUCCESS;
		return UPDATE_FAILED;

	}

}
