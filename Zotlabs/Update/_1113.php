<?php

namespace Zotlabs\Update;

class _1113 {
function run() {
	$r = q("ALTER TABLE `likes` ADD `channel_id` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `id` ,
CHANGE `iid` `iid` INT( 10 ) UNSIGNED NOT NULL DEFAULT '0',
ADD INDEX ( `channel_id` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}