<?php

namespace Zotlabs\Update;

class _1021 {
function run() {

	$r = q("ALTER TABLE `abook` CHANGE `abook_connnected` `abook_connected` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
		drop index `abook_connnected`, add index ( `abook_connected` ) ");
	
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}