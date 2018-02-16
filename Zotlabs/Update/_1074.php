<?php

namespace Zotlabs\Update;

class _1074 {
function run() {
	$r1 = q("ALTER TABLE `site` ADD `site_sync` DATETIME NOT NULL AFTER `site_update` ");

	$r2 = q("ALTER TABLE `updates` ADD `ud_last` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' AFTER `ud_date` ,
ADD INDEX ( `ud_last` ) ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}