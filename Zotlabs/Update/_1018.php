<?php

namespace Zotlabs\Update;

class _1018 {
function run() {
	$r = q("ALTER TABLE `event` ADD `event_hash` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `event_xchan` ,
ADD INDEX ( `event_hash` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}