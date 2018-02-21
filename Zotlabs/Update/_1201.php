<?php

namespace Zotlabs\Update;

class _1201 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
			$r = q("ALTER TABLE item 
				DROP INDEX item_thread_top,
				ADD INDEX uid_item_thread_top (uid, item_thread_top),
				ADD INDEX uid_item_blocked (uid, item_blocked),
				ADD INDEX item_deleted_pending_remove_changed (item_deleted, item_pending_remove, changed)
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
