<?php

namespace Zotlabs\Update;

class _1134 {
function run() {
	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 
		$r1 = q("ALTER TABLE xlink ADD xlink_static numeric(1) NOT NULL DEFAULT '0' ");
		$r2 = q("create index xlink_static on xlink ( xlink_static ) ");
		$r = $r1 && $r2;
	}
	else
		$r = q("ALTER TABLE xlink ADD xlink_static TINYINT( 1 ) NOT NULL DEFAULT '0', ADD INDEX ( xlink_static ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}