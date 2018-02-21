<?php

namespace Zotlabs\Update;

class _1031 {
function run() {
	$r = q("ALTER TABLE `account` ADD `account_external` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `account_email` ,
ADD INDEX ( `account_external` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}