<?php

namespace Zotlabs\Update;

class _1041 {
function run() {
	$r = q("ALTER TABLE `outq` ADD `outq_driver` CHAR( 32 ) NOT NULL DEFAULT '' AFTER `outq_channel` ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}