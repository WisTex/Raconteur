<?php

namespace Zotlabs\Update;

class _1121 {
function run() {
	$r = q("ALTER TABLE `site` ADD `site_realm` CHAR( 255 ) NOT NULL DEFAULT '',
ADD INDEX ( `site_realm` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}