<?php

namespace Zotlabs\Update;

class _1205 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
			$r = q("ALTER TABLE item 
				DROP INDEX item_private,
				ADD INDEX uid_item_private (uid, item_private),
				ADD INDEX item_wall (item_wall),
				ADD INDEX item_pending_remove_changed (item_pending_remove, changed)
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
