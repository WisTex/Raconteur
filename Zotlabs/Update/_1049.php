<?php

namespace Zotlabs\Update;

class _1049 {
function run() {
	$r = q("ALTER TABLE `term` ADD `parent_hash` CHAR( 255 ) NOT NULL DEFAULT '' AFTER `term_hash` , ADD INDEX ( `parent_hash` ) ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}