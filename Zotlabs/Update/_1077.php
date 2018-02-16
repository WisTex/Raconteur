<?php

namespace Zotlabs\Update;

class _1077 {
function run() {
	$r = q("ALTER TABLE `item` ADD `source_xchan` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `author_xchan` ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}