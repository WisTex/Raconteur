<?php

namespace Zotlabs\Update;

class _1026 {
function run() {
	$r = q("ALTER TABLE `item` ADD `mimetype` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `author_xchan` ,
ADD INDEX ( `mimetype` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}