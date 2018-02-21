<?php

namespace Zotlabs\Update;

class _1179 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("CREATE TABLE atoken (
  atoken_id serial NOT NULL,
  atoken_aid bigint NOT NULL DEFAULT 0,
  atoken_uid bigint NOT NULL DEFAULT 0,
  atoken_name varchar(255) NOT NULL DEFAULT '',
  atoken_token varchar(255) NOT NULL DEFAULT '',
  atoken_expires timestamp NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (atoken_id)) ");
	$r2 = q("create index atoken_aid on atoken (atoken_aid)");
	$r3 = q("create index atoken_uid on atoken (atoken_uid)");
	$r4 = q("create index atoken_name on atoken (atoken_name)");
	$r5 = q("create index atoken_token on atoken (atoken_token)");
	$r6 = q("create index atoken_expires on atoken (atoken_expires)");

	$r = $r1 && $r2 && $r3 && $r4 && $r5 && $r6;
 
	}
	else {
		$r = q("CREATE TABLE IF NOT EXISTS `atoken` (
  `atoken_id` int(11) NOT NULL AUTO_INCREMENT,
  `atoken_aid` int(11) NOT NULL DEFAULT 0,
  `atoken_uid` int(11) NOT NULL DEFAULT 0,
  `atoken_name` char(255) NOT NULL DEFAULT '',
  `atoken_token` char(255) NOT NULL DEFAULT '',
  `atoken_expires` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`atoken_id`),
  KEY `atoken_aid` (`atoken_aid`),
  KEY `atoken_uid` (`atoken_uid`),
  KEY `atoken_name` (`atoken_name`),
  KEY `atoken_token` (`atoken_token`),
  KEY `atoken_expires` (`atoken_expires`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");
	}
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
	
}


}