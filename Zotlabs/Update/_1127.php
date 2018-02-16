<?php

namespace Zotlabs\Update;

class _1127 {
function run() {
	$r = q("ALTER TABLE `item` ADD `comments_closed` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' AFTER `changed` ,
ADD INDEX ( `comments_closed` ), ADD INDEX ( `changed` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}