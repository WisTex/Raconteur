<?php

namespace Zotlabs\Update;

class _1172 {
function run() {

	$r1 = q("ALTER TABLE term CHANGE `type` `ttype` int(3) NOT NULL DEFAULT '0' ");

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r2 = q("ALTER TABLE groups CHANGE `name` `gname` TEXT NOT NULL ");
		$r3 = q("ALTER TABLE profile CHANGE `name` `fullname` TEXT NOT NULL ");
		$r4 = q("ALTER TABLE profile CHANGE `with` `partner` TEXT NOT NULL ");
		$r5 = q("ALTER TABLE profile CHANGE `work` `employment` TEXT NOT NULL ");
	}
	else {
		$r2 = q("ALTER TABLE groups CHANGE `name` `gname` char(255) NOT NULL DEFAULT '' ");
		$r3 = q("ALTER TABLE profile CHANGE `name` `fullname` char(255) NOT NULL DEFAULT '' ");
		$r4 = q("ALTER TABLE profile CHANGE `with` `partner` char(255) NOT NULL DEFAULT '' ");
		$r5 = q("ALTER TABLE profile CHANGE `work` `employment` TEXT NOT NULL ");
	}
	if($r1 && $r2 && $r3 && $r4 && $r5)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}