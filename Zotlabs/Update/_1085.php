<?php

namespace Zotlabs\Update;

class _1085 {
function run() {
	$r1 = q("ALTER TABLE `photo` CHANGE `desc` `description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ");

	$r2 = q("RENAME TABLE `group` TO `groups`");

	$r3 = q("ALTER TABLE `event` CHANGE `desc` `description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ");

	if($r1 && $r2 && $r3)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}