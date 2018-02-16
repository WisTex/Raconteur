<?php

namespace Zotlabs\Update;

class _1070 {
function run() {
	$r = q("ALTER TABLE `updates` ADD `ud_flags` INT NOT NULL DEFAULT '0',
ADD INDEX ( `ud_flags` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}