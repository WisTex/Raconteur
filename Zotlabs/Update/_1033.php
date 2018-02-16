<?php

namespace Zotlabs\Update;

class _1033 {
function run() {
	$r = q("CREATE TABLE if not exists `shares` (
`share_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`share_type` INT NOT NULL DEFAULT '0',
`share_target` INT UNSIGNED NOT NULL DEFAULT '0',
`share_xchan` CHAR( 255 ) NOT NULL DEFAULT '',
KEY `share_type` (`share_type`),
KEY `share_target` (`share_target`),
KEY `share_xchan` (`share_xchan`)
) ENGINE = MYISAM DEFAULT CHARSET = utf8");

	// if these fail don't bother reporting it

	q("drop table gcign");
	q("drop table gcontact");
	q("drop table glink");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}