<?php

namespace Zotlabs\Update;

class _1059 {
function run() {
	$r = q("ALTER TABLE `mail` ADD `attach` MEDIUMTEXT NOT NULL DEFAULT '' AFTER `body` ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}