<?php

namespace Zotlabs\Update;

class _1141 {
function run() {
		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 
		$r1 = q("ALTER TABLE menu ADD menu_created timestamp NOT NULL DEFAULT '0001-01-01 00:00:00', ADD menu_edited timestamp NOT NULL DEFAULT '0001-01-01 00:00:00'");
		$r2 = q("create index menu_created on menu ( menu_created ) ");
		$r3 = q("create index menu_edited on menu ( menu_edited ) ");
		$r = $r1 && $r2;
	}
	else
		$r = q("ALTER TABLE menu ADD menu_created DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00', ADD menu_edited DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00', ADD INDEX ( menu_created ), ADD INDEX ( menu_edited ) ");

	$t = datetime_convert();
	q("update menu set menu_created = '%s', menu_edited = '%s' where true",
		dbesc($t),
		dbesc($t)
	);


	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}