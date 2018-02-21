<?php

namespace Zotlabs\Update;

class _1032 {
function run() {
	$r = q("CREATE TABLE if not exists `xign` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`uid` INT NOT NULL DEFAULT '0',
`xchan` CHAR( 255 ) NOT NULL DEFAULT '',
KEY `uid` (`uid`),
KEY `xchan` (`xchan`)
) ENGINE = MYISAM DEFAULT CHARSET = utf8");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}