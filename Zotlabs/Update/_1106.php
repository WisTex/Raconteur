<?php

namespace Zotlabs\Update;

class _1106 {
function run() {
	$r = q("ALTER TABLE `notify` CHANGE `parent` `parent` CHAR( 255 ) NOT NULL DEFAULT ''");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}