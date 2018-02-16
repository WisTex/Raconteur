<?php

namespace Zotlabs\Update;

class _1043 {
function run() {
	$r = q("ALTER TABLE `item` ADD `comment_policy` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `coord` ,
ADD INDEX ( `comment_policy` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}