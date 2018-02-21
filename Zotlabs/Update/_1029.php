<?php

namespace Zotlabs\Update;

class _1029 {
function run() {
	$r = q("ALTER TABLE `channel` ADD `channel_deleted` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' AFTER `channel_pageflags` ,
ADD INDEX ( `channel_deleted` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}