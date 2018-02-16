<?php

namespace Zotlabs\Update;

class _1092 {
function run() {
	$r1 = q("CREATE TABLE IF NOT EXISTS `chat` (
  `chat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_room` int(10) unsigned NOT NULL DEFAULT '0',
  `chat_xchan` char(255) NOT NULL DEFAULT '',
  `chat_text` mediumtext NOT NULL,
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  PRIMARY KEY (`chat_id`),
  KEY `chat_room` (`chat_room`),
  KEY `chat_xchan` (`chat_xchan`),
  KEY `created` (`created`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

	$r2 = q("CREATE TABLE IF NOT EXISTS `chatpresence` (
  `cp_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cp_room` int(10) unsigned NOT NULL DEFAULT '0',
  `cp_xchan` char(255) NOT NULL DEFAULT '',
  `cp_last` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `cp_status` char(255) NOT NULL,
  PRIMARY KEY (`cp_id`),
  KEY `cp_room` (`cp_room`),
  KEY `cp_xchan` (`cp_xchan`),
  KEY `cp_last` (`cp_last`),
  KEY `cp_status` (`cp_status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

	$r3 = q("CREATE TABLE IF NOT EXISTS `chatroom` (
  `cr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cr_aid` int(10) unsigned NOT NULL DEFAULT '0',
  `cr_uid` int(10) unsigned NOT NULL DEFAULT '0',
  `cr_name` char(255) NOT NULL DEFAULT '',
  `cr_created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `cr_edited` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  PRIMARY KEY (`cr_id`),
  KEY `cr_aid` (`cr_aid`),
  KEY `cr_uid` (`cr_uid`),
  KEY `cr_name` (`cr_name`),
  KEY `cr_created` (`cr_created`),
  KEY `cr_edited` (`cr_edited`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");


	if($r1 && $r2 && $r3)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}





}