<?php

namespace Zotlabs\Update;

class _1195 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("CREATE INDEX item_resource_id ON item (resource_id)");
	}
	else {
		$r1 = q("ALTER TABLE item ADD INDEX (resource_id)");
	}

	if($r1)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}