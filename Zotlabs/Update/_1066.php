<?php

namespace Zotlabs\Update;

class _1066 {
function run() {
	$r = q("ALTER TABLE `site` ADD `site_access` INT NOT NULL DEFAULT '0' AFTER `site_url` ,
ADD INDEX ( `site_access` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}