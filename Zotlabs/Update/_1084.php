<?php

namespace Zotlabs\Update;

class _1084 {
function run() {


	$r = q("CREATE TABLE if not exists `sys_perms` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`cat` CHAR( 255 ) NOT NULL ,
			`k` CHAR( 255 ) NOT NULL ,
			`v` MEDIUMTEXT NOT NULL,
			`public_perm` TINYINT( 1 ) UNSIGNED NOT NULL
) ENGINE = MYISAM DEFAULT CHARSET = utf8");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}