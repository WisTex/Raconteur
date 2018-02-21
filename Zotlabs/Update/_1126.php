<?php

namespace Zotlabs\Update;

class _1126 {
function run() {
	$r = q("ALTER TABLE `mail` ADD `convid` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `id` ,
ADD INDEX ( `convid` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}