<?php

namespace Zotlabs\Update;

class _1069 {
function run() {
	$r = q("ALTER TABLE `site` ADD `site_sellpage` CHAR( 255 ) NOT NULL DEFAULT '',
ADD INDEX ( `site_sellpage` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}