<?php

namespace Zotlabs\Update;

class _1203 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
			$r = q("ALTER TABLE item 
				DROP INDEX item_wall,
				DROP INDEX item_starred,
				DROP INDEX item_retained,
				ADD INDEX uid_item_wall (uid, item_wall),
				ADD INDEX uid_item_starred (uid, item_starred),
				ADD INDEX uid_item_retained (uid, item_retained)
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
