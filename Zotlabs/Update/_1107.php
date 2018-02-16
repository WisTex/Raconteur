<?php

namespace Zotlabs\Update;

class _1107 {
function run() {
	$r = q("CREATE TABLE IF NOT EXISTS `app` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `app_id` char(64) NOT NULL DEFAULT '',
  `app_sig` char(255) NOT NULL DEFAULT '',
  `app_author` char(255) NOT NULL DEFAULT '',
  `app_name` char(255) NOT NULL DEFAULT '',
  `app_desc` text NOT NULL,
  `app_url` char(255) NOT NULL DEFAULT '',
  `app_photo` char(255) NOT NULL DEFAULT '',
  `app_version` char(255) NOT NULL DEFAULT '',
  `app_channel` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `app_id` (`app_id`),
  KEY `app_name` (`app_name`),
  KEY `app_url` (`app_url`),
  KEY `app_photo` (`app_photo`),
  KEY `app_version` (`app_version`),
  KEY `app_channel` (`app_channel`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}