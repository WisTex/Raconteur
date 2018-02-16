<?php

namespace Zotlabs\Update;

class _1064 {
function run() {
	$r = q("ALTER TABLE `updates` ADD `ud_guid` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `ud_hash` ,
ADD INDEX ( `ud_guid` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}