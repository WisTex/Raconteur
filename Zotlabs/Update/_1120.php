<?php

namespace Zotlabs\Update;

class _1120 {
function run() {
	$r = q("ALTER TABLE `item` ADD `public_policy` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `coord` ,
ADD INDEX ( `public_policy` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}