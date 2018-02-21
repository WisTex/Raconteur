<?php

namespace Zotlabs\Update;

class _1086 {
function run() {
	$r = q("ALTER TABLE `account` ADD `account_level` INT UNSIGNED NOT NULL DEFAULT '0',
ADD INDEX ( `account_level` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}