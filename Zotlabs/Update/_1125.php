<?php

namespace Zotlabs\Update;

class _1125 {
function run() {
	$r = q("ALTER TABLE `profdef` ADD `field_inputs` MEDIUMTEXT NOT NULL DEFAULT ''");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}



}