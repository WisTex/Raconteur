<?php

namespace Zotlabs\Update;

class _1150 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 
		$r1 = q("ALTER TABLE app ADD app_created timestamp NOT NULL DEFAULT '0001-01-01 00:00:00',
			ADD app_edited timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
	}
	else {
		$r1 = q("ALTER TABLE app ADD app_created DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
			ADD app_edited DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' ");
	}

	$r2 = q("create index app_created on app ( app_created ) ");
	$r3 = q("create index app_edited on app ( app_edited ) ");

	$r = $r1 && $r2 && $r3;
    if($r)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;

}


}