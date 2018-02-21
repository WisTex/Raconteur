<?php

namespace Zotlabs\Update;

class _1114 {
function run() {
	$r = q("ALTER TABLE `likes` ADD `target_id` CHAR( 128 ) NOT NULL DEFAULT '' AFTER `target_type` ,
ADD INDEX ( `target_id` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}
	

}