<?php

namespace Zotlabs\Update;

class _1193 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("CREATE INDEX item_uid_unseen ON item (uid, item_unseen)");
	}
	else {
		$r1 = q("ALTER TABLE item ADD INDEX uid_item_unseen (uid, item_unseen)");
	}

	if($r1)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}