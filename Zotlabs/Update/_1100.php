<?php

namespace Zotlabs\Update;

class _1100 {
function run() {
	$r = q("ALTER TABLE `xchat` ADD `xchat_edited` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
ADD INDEX ( `xchat_edited` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}
	


}