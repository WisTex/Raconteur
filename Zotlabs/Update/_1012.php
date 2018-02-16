<?php

namespace Zotlabs\Update;

class _1012 {
function run() {
	$r = q("ALTER TABLE `xchan` ADD `xchan_connurl` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `xchan_url` ,
ADD INDEX ( `xchan_connurl` )");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}