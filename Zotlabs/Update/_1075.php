<?php

namespace Zotlabs\Update;

class _1075 {
function run() {
	$r = q("ALTER TABLE `channel` ADD `channel_a_republish` INT UNSIGNED NOT NULL DEFAULT '128',
ADD INDEX ( `channel_a_republish` )");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}