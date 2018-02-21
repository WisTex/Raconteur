<?php

namespace Zotlabs\Update;

class _1024 {
function run() {
	$r = q("ALTER TABLE `attach` ADD `revision` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `filesize` ,
ADD INDEX ( `revision` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}