<?php

namespace Zotlabs\Update;

class _1183 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("alter table hook ALTER COLUMN priority TYPE smallint");
		$r2 = q("alter table hook ALTER COLUMN priority SET NOT NULL");
		$r3 = q("alter table hook ALTER COLUMN priority SET DEFAULT '0'");
		$r1 = $r1 && $r2 && $r3;
	}
	else {
		$r1 = q("alter table hook CHANGE priority priority smallint NOT NULL DEFAULT '0' ");
	}
	$r2 = q("create index priority_idx on hook (priority)");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}