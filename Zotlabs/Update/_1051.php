<?php

namespace Zotlabs\Update;

class _1051 {
function run() {
	$r = q("ALTER TABLE `photo` ADD `photo_flags` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `profile` , ADD INDEX ( `photo_flags` ) ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}