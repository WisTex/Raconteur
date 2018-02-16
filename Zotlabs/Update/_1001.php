<?php

namespace Zotlabs\Update;

class _1001 {
function run() {
	$r = q("CREATE TABLE if not exists `verify` (
		`id` INT(10) UNSIGNED NOT NULL ,
		`channel` INT(10) UNSIGNED NOT NULL DEFAULT '0',
		`type` CHAR( 32 ) NOT NULL DEFAULT '',
		`token` CHAR( 255 ) NOT NULL DEFAULT '',
		`meta` CHAR( 255 ) NOT NULL DEFAULT '',
		`created` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
		PRIMARY KEY ( `id` )
		) ENGINE = MYISAM DEFAULT CHARSET=utf8");

	$r2 = q("alter table `verify` add index (`channel`), add index (`type`), add index (`token`),
		add index (`meta`), add index (`created`)");

	if($r && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}