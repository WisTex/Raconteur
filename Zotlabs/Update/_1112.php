<?php

namespace Zotlabs\Update;

class _1112 {
function run() {
	$r = q("CREATE TABLE IF NOT EXISTS `likes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `liker` char(128) NOT NULL DEFAULT '',
  `likee` char(128) NOT NULL DEFAULT '',
  `iid` int(11) NOT NULL DEFAULT '0',
  `verb` char(255) NOT NULL DEFAULT '',
  `target_type` char(255) NOT NULL DEFAULT '',
  `target` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `liker` (`liker`),
  KEY `likee` (`likee`),
  KEY `iid` (`iid`),
  KEY `verb` (`verb`),
  KEY `target_type` (`target_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}