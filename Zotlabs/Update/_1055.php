<?php

namespace Zotlabs\Update;

class _1055 {
function run() {
	$r = q("ALTER TABLE `mail` CHANGE `title` `title` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}