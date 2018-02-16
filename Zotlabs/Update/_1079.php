<?php

namespace Zotlabs\Update;

class _1079 {
function run() {
	$r = q("ALTER TABLE `site` ADD `site_location` CHAR( 255 ) NOT NULL DEFAULT ''");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}