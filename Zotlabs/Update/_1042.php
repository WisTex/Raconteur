<?php

namespace Zotlabs\Update;

class _1042 {
function run() {
	$r = q("ALTER TABLE `hubloc` ADD `hubloc_updated` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
ADD `hubloc_connected` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',  ADD INDEX ( `hubloc_updated` ),  ADD INDEX ( `hubloc_connected` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}