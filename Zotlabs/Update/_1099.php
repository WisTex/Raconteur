<?php

namespace Zotlabs\Update;

class _1099 {
function run() {
	$r = q("CREATE TABLE IF NOT EXISTS `xchat` (
  `xchat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `xchat_url` char(255) NOT NULL DEFAULT '',
  `xchat_desc` char(255) NOT NULL DEFAULT '',
  `xchat_xchan` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`xchat_id`),
  KEY `xchat_url` (`xchat_url`),
  KEY `xchat_desc` (`xchat_desc`),
  KEY `xchat_xchan` (`xchat_xchan`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}