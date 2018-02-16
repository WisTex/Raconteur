<?php

namespace Zotlabs\Update;

class _1004 {
function run() {
	$r = q("CREATE TABLE if not exists `site` (
`site_url` CHAR( 255 ) NOT NULL ,
`site_flags` INT NOT NULL DEFAULT '0',
`site_update` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
`site_directory` CHAR( 255 ) NOT NULL DEFAULT '',
PRIMARY KEY ( `site_url` )
) ENGINE = MYISAM DEFAULT CHARSET=utf8");

	$r2 = q("alter table site add index (site_flags), add index (site_update), add index (site_directory) ");

	if($r && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}