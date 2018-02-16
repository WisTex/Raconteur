<?php

namespace Zotlabs\Update;

class _1034 {
function run() {
	$r = q("CREATE TABLE if not exists `updates` (
`ud_hash` CHAR( 128 ) NOT NULL ,
`ud_date` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
PRIMARY KEY ( `ud_hash` ),
KEY `ud_date` ( `ud_date` )
) ENGINE = MYISAM DEFAULT CHARSET = utf8");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}