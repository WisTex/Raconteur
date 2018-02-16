<?php

namespace Zotlabs\Update;

class _1010 {
function run() {
	$r = q("ALTER TABLE `abook` ADD `abook_dob` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' AFTER `abook_connnected` ,
ADD INDEX ( `abook_dob` )");

	$r2 = q("ALTER TABLE `profile` ADD `dob_tz` CHAR( 255 ) NOT NULL DEFAULT 'UTC' AFTER `dob`");

	if($r && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}