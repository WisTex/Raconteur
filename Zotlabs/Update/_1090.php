<?php

namespace Zotlabs\Update;

class _1090 {
function run() {
	$r = q("ALTER TABLE `menu` ADD `menu_flags` INT NOT NULL DEFAULT '0',
ADD INDEX ( `menu_flags` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}