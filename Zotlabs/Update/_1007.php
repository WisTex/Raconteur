<?php

namespace Zotlabs\Update;

class _1007 {
function run() {
	$r = q("ALTER TABLE `channel` ADD `channel_r_storage` INT UNSIGNED NOT NULL DEFAULT '128', ADD `channel_w_storage` INT UNSIGNED NOT NULL DEFAULT '128', add index ( channel_r_storage ), add index ( channel_w_storage )");

	if($r && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}