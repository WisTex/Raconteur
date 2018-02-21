<?php

namespace Zotlabs\Update;

class _1167 {
function run() {

	$r1 = q("alter table app add app_deleted int not null default '0' ");
	$r2 = q("alter table app add app_system int not null default '0' ");

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r3 = q("create index \"app_deleted_idx\" on app (\"app_deleted\") "); 
		$r4 = q("create index \"app_system_idx\" on app (\"app_system\") "); 
	}
	else { 
		$r3 = q("alter table app add index ( app_deleted ) ");
		$r4 = q("alter table app add index ( app_system ) ");
	}

	if($r1 && $r2 && $r3 && $r4)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}