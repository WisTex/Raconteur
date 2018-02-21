<?php

namespace Zotlabs\Update;

class _1050 {
function run() {
	$r = q("ALTER TABLE `xtag` DROP PRIMARY KEY , ADD `xtag_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST , ADD INDEX ( `xtag_hash` ) ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}