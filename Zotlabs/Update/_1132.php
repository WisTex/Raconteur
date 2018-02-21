<?php

namespace Zotlabs\Update;

class _1132 {
function run() {
	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { // correct previous failed update
		$r1 = q("ALTER TABLE abook ADD abook_rating_text TEXT NOT NULL DEFAULT '' ");
		$r2 = q("ALTER TABLE xlink ADD xlink_rating_text TEXT NOT NULL DEFAULT '' ");
		if(!$r1 || !$r2)
			return UPDATE_FAILED;
	}
	return UPDATE_SUCCESS;
}


}