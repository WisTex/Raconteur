<?php

namespace Zotlabs\Update;

class _1123 {
function run() {
	$r1 = q("ALTER TABLE `hubloc` ADD `hubloc_network` CHAR( 32 ) NOT NULL DEFAULT '' AFTER `hubloc_addr` ,
ADD INDEX ( `hubloc_network` )");
	$r2 = q("update hubloc set hubloc_network = 'zot' where true");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}