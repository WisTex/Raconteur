<?php

namespace Zotlabs\Update;

class _1035 {
function run() {
	$r = q("CREATE TABLE if not exists `xconfig` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`xchan` CHAR( 255 ) NOT NULL ,
`cat` CHAR( 255 ) NOT NULL ,
`k` CHAR( 255 ) NOT NULL ,
`v` MEDIUMTEXT NOT NULL,
KEY `xchan` ( `xchan` ),
KEY `cat` ( `cat` ),
KEY `k` ( `k` )
) ENGINE = MYISAM DEFAULT CHARSET = utf8");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}