<?php

namespace Zotlabs\Update;

class _1037 {
function run() {
	$r1 = q("ALTER TABLE `item` CHANGE `uri` `mid` CHAR( 255 ) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
CHANGE `parent_uri` `parent_mid` CHAR( 255 ) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
 DROP INDEX `uri` ,
ADD INDEX `mid` ( `mid` ),
DROP INDEX `parent_uri` ,
ADD INDEX `parent_mid` ( `parent_mid` ),
 DROP INDEX `uid_uri` ,
ADD INDEX `uid_mid` ( `mid` , `uid` ) ");

	$r2 = q("ALTER TABLE `mail` CHANGE `uri` `mid` CHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
CHANGE `parent_uri` `parent_mid` CHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
DROP INDEX `uri` ,
ADD INDEX `mid` ( `mid` ),
 DROP INDEX `parent_uri` ,
ADD INDEX `parent_mid` ( `parent_mid` ) ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}