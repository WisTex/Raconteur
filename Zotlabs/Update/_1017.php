<?php

namespace Zotlabs\Update;

class _1017 {
function run() {
	$r = q("ALTER TABLE `event` CHANGE `cid` `event_xchan` CHAR( 255 ) NOT NULL DEFAULT '', ADD INDEX ( `event_xchan` ), drop index cid  ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}