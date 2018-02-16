<?php

namespace Zotlabs\Update;

class _1080 {
function run() {
	$r = q("ALTER TABLE `mail` ADD `expires` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
ADD INDEX ( `expires` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}