<?php

namespace Zotlabs\Update;

class _1067 {
function run() {
	$r = q("ALTER TABLE `updates` DROP PRIMARY KEY , ADD `ud_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,  ADD INDEX ( `ud_hash` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}