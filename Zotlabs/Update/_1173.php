<?php

namespace Zotlabs\Update;

class _1173 {
function run() {


	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("ALTER TABLE notify CHANGE `name` `xname` TEXT NOT NULL ");
		$r2 = q("ALTER TABLE notify CHANGE `date` `created` timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
		$r3 = q("ALTER TABLE notify CHANGE `type` `ntype` numeric(3) NOT NULL DEFAULT '0' ");
	}
	else {
		$r1 = q("ALTER TABLE notify CHANGE `name` `xname` char(255) NOT NULL DEFAULT '' ");
		$r2 = q("ALTER TABLE notify CHANGE `date` `created` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' ");
		$r3 = q("ALTER TABLE notify CHANGE `type` `ntype` smallint(3) NOT NULL DEFAULT '0' ");
	}

	if($r1 && $r2 && $r3)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}