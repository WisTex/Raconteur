<?php

namespace Zotlabs\Update;

class _1063 {
function run() {
	$r = q("ALTER TABLE `xchan` ADD `xchan_follow` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `xchan_connurl` ,
ADD `xchan_connpage` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `xchan_follow` ,
ADD INDEX ( `xchan_follow` ), ADD INDEX ( `xchan_connpage`) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}