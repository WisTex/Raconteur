<?php

namespace Zotlabs\Update;

class _1128 {
function run() {
	$r = q("ALTER TABLE `item` ADD `diaspora_meta` MEDIUMTEXT NOT NULL DEFAULT '' AFTER `sig` ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}