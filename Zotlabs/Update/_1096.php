<?php

namespace Zotlabs\Update;

class _1096 {
function run() {
	$r = q("ALTER TABLE `account` CHANGE `account_level` `account_level` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0'");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}