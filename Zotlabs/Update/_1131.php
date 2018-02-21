<?php

namespace Zotlabs\Update;

class _1131 {
function run() {
	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) // make sure this gets skipped for anyone who hasn't run it yet, it will fail on pg
		return UPDATE_SUCCESS;
		
	$r1 = q("ALTER TABLE `abook` ADD `abook_rating_text` TEXT NOT NULL DEFAULT '' AFTER `abook_rating` ");
	$r2 = q("ALTER TABLE `xlink` ADD `xlink_rating_text` TEXT NOT NULL DEFAULT '' AFTER `xlink_rating` ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}