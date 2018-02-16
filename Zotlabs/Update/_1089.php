<?php

namespace Zotlabs\Update;

class _1089 {
function run() {
	$r = q("ALTER TABLE `attach` ADD `creator` CHAR( 128 ) NOT NULL DEFAULT '' AFTER `hash` ,
ADD INDEX ( `creator` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}