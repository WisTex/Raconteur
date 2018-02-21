<?php

namespace Zotlabs\Update;

class _1072 {
function run() {
	$r = q("ALTER TABLE `xtag` ADD `xtag_flags` INT NOT NULL DEFAULT '0',
ADD INDEX ( `xtag_flags` ) ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}