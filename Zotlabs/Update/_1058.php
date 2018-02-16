<?php

namespace Zotlabs\Update;

class _1058 {
function run() {
	$r1 = q("ALTER TABLE `menu` ADD `menu_name` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `menu_channel_id` ,
ADD INDEX ( `menu_name` ) ");

	$r2 = q("ALTER TABLE `menu_item` ADD `mitem_flags` INT NOT NULL DEFAULT '0' AFTER `mitem_desc` ,
ADD INDEX ( `mitem_flags` ) ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}