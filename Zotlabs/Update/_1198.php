<?php

namespace Zotlabs\Update;

class _1198 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
		$r = q("ALTER TABLE item 
			DROP INDEX item_blocked,
			DROP INDEX item_unpublished,
			DROP INDEX item_deleted,
			DROP INDEX item_delayed,
			DROP INDEX item_hidden,
			DROP INDEX item_pending_remove,
			DROP INDEX item_type
		");
	}

	return UPDATE_SUCCESS;
}


}
