<?php

namespace Zotlabs\Update;

class _1124 {
function run() {
	$r1 = q("CREATE TABLE IF NOT EXISTS `sign` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `iid` int(10) unsigned NOT NULL DEFAULT '0',
  `retract_iid` int(10) unsigned NOT NULL DEFAULT '0',
  `signed_text` mediumtext NOT NULL,
  `signature` text NOT NULL,
  `signer` char(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `iid` (`iid`),
  KEY `retract_iid` (`retract_iid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ");

	$r2 = q("CREATE TABLE IF NOT EXISTS `conv` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `guid` char(255) NOT NULL,
  `recips` mediumtext NOT NULL,
  `uid` int(11) NOT NULL,
  `creator` char(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `updated` datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
  `subject` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created` (`created`),
  KEY `updated` (`updated`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;


}


}