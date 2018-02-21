<?php

namespace Zotlabs\Update;

class _1065 {
function run() {
	$r = q("ALTER TABLE `item` DROP `wall`, ADD `layout_mid` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `target` ,
ADD INDEX ( `layout_mid` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}