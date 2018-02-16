<?php

namespace Zotlabs\Update;

class _1025 {
function run() {
	$r = q("ALTER TABLE `attach` ADD `folder` CHAR( 64 ) NOT NULL DEFAULT '' AFTER `revision` ,
ADD `flags` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `folder` , add index ( folder ), add index ( flags )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}