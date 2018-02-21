<?php

namespace Zotlabs\Update;

class _1165 {
function run() {

	$r1 = q("alter table hook add hook_version int not null default '0' ");

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES)
		$r2 = q("create index \"hook_version_idx\" on hook (\"hook_version\") "); 
	else 
		$r2 = q("alter table hook add index ( hook_version ) ");
    if($r1 && $r2)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}


}