<?php

namespace Zotlabs\Update;

class _1023 {
function run() {
	$r = q("ALTER TABLE `item` ADD `revision` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `lang` , add index ( revision ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}