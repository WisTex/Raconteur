<?php

namespace Zotlabs\Update;

class _1094 {
function run() {
	$r = q("ALTER TABLE `chatroom` ADD `cr_expire` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `cr_edited` ,
ADD INDEX ( `cr_expire` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}