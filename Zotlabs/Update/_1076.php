<?php

namespace Zotlabs\Update;

class _1076 {
function run() {
	$r = q("ALTER TABLE `item` CHANGE `inform` `sig` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}