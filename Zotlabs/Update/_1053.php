<?php

namespace Zotlabs\Update;

class _1053 {
function run() {
	$r = q("ALTER TABLE `profile` ADD `chandesc` TEXT NOT NULL DEFAULT '' AFTER `pdesc` ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}