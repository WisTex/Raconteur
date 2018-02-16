<?php

namespace Zotlabs\Update;

class _1015 {
function run() {
	$r = q("ALTER TABLE `channel` ADD `channel_r_pages` INT UNSIGNED NOT NULL DEFAULT '128',
ADD `channel_w_pages` INT UNSIGNED NOT NULL DEFAULT '128'");

	$r2 = q("ALTER TABLE `channel` ADD INDEX ( `channel_r_pages` ) , ADD INDEX ( `channel_w_pages` ) ");

	if($r && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}