<?php

namespace Zotlabs\Update;

class _1087 {
function run() {
	$r = q("ALTER TABLE `xprof` ADD `xprof_about` TEXT NOT NULL DEFAULT '',
ADD `xprof_homepage` CHAR( 255 ) NOT NULL DEFAULT '',
ADD `xprof_hometown` CHAR( 255 ) NOT NULL DEFAULT '',
ADD INDEX ( `xprof_hometown` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}