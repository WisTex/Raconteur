<?php

namespace Zotlabs\Update;

class _1162 {
function run() {
	$r1 = q("alter table iconfig add sharing int not null default '0' ");

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES)
		$r2 = q("create index \"iconfig_sharing\" on iconfig (\"sharing\") "); 
	else 
		$r2 = q("alter table iconfig add index ( sharing ) ");
    if($r1 && $r2)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}


}