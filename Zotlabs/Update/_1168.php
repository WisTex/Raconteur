<?php

namespace Zotlabs\Update;

class _1168 {
function run() {

	$r1 = q("alter table obj add obj_quantity int not null default '0' ");

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r2 = q("create index \"obj_quantity_idx\" on obj (\"obj_quantity\") "); 
	}
	else { 
		$r2 = q("alter table obj add index ( obj_quantity ) ");
	}

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}