<?php

namespace Zotlabs\Update;

class _1200 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
		$r = q("ALTER TABLE item 
			DROP INDEX item_type,
			ADD INDEX uid_item_type (uid, item_type)
		");

		if($r)
			return UPDATE_SUCCESS;
		return UPDATE_FAILED;
	}
	else {
		return UPDATE_SUCCESS;
	}

}

}
