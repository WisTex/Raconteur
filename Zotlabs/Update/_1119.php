<?php

namespace Zotlabs\Update;

class _1119 {
function run() {
	$r1 = q("CREATE TABLE IF NOT EXISTS `profdef` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `field_name` char(255) NOT NULL DEFAULT '',
  `field_type` char(16) NOT NULL DEFAULT '',
  `field_desc` char(255) NOT NULL DEFAULT '',
  `field_help` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `field_name` (`field_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

	$r2 = q("CREATE TABLE IF NOT EXISTS `profext` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` int(10) unsigned NOT NULL DEFAULT '0',
  `hash` char(255) NOT NULL DEFAULT '',
  `k` char(255) NOT NULL DEFAULT '',
  `v` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `channel_id` (`channel_id`),
  KEY `hash` (`hash`),
  KEY `k` (`k`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}