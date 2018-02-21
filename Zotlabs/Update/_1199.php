<?php

namespace Zotlabs\Update;

class _1199 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
		$r = q("ALTER TABLE item 
			DROP INDEX uid,
			ADD INDEX (item_type)
		");
	}

	return UPDATE_SUCCESS;
}


}