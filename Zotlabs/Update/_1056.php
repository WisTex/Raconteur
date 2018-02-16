<?php

namespace Zotlabs\Update;

class _1056 {
function run() {
	$r = q("ALTER TABLE `xchan` ADD `xchan_instance_url` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `xchan_network` ,
ADD INDEX ( `xchan_instance_url` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}