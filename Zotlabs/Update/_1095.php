<?php

namespace Zotlabs\Update;

class _1095 {
function run() {
	$r = q("ALTER TABLE `channel` ADD `channel_a_bookmark` INT UNSIGNED NOT NULL DEFAULT '128',
ADD INDEX ( `channel_a_bookmark` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}