<?php

namespace Zotlabs\Update;

class _1205 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {

			q("ALTER TABLE item DROP INDEX title");
			q("ALTER TABLE item DROP INDEX body");
			q("ALTER TABLE item DROP INDEX allow_cid");
			q("ALTER TABLE item DROP INDEX allow_gid");
			q("ALTER TABLE item DROP INDEX deny_cid");
			q("ALTER TABLE item DROP INDEX deny_gid");
			q("ALTER TABLE item DROP INDEX item_flags");
			q("ALTER TABLE item DROP INDEX item_restrict");
			q("ALTER TABLE item DROP INDEX aid");

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
