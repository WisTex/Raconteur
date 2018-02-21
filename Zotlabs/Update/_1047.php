<?php

namespace Zotlabs\Update;

class _1047 {
function run() {
	$r = q("ALTER TABLE `xprof` ADD `xprof_age` TINYINT( 3 ) UNSIGNED NOT NULL DEFAULT '0' AFTER `xprof_hash` ,
ADD INDEX ( `xprof_age` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}