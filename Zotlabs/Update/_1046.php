<?php

namespace Zotlabs\Update;

class _1046 {
function run() {
	$r = q("ALTER TABLE `term` ADD `term_hash` CHAR( 255 ) NOT NULL DEFAULT '',
ADD INDEX ( `term_hash` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}