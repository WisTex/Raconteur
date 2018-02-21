<?php

namespace Zotlabs\Update;

class _1028 {
function run() {
	$r = q("ALTER TABLE `xlink` ADD `xlink_rating` INT NOT NULL DEFAULT '0' AFTER `xlink_link` ,
ADD INDEX ( `xlink_rating` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}