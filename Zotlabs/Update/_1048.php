<?php

namespace Zotlabs\Update;

class _1048 {
function run() {
	$r = q("CREATE TABLE IF NOT EXISTS `obj` (
  `obj_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `obj_page` char(64) NOT NULL DEFAULT '',
  `obj_verb` char(255) NOT NULL DEFAULT '',
  `obj_type` int(10) unsigned NOT NULL DEFAULT '0',
  `obj_obj` char(255) NOT NULL DEFAULT '',
  `obj_channel` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`obj_id`),
  KEY `obj_verb` (`obj_verb`),
  KEY `obj_page` (`obj_page`),
  KEY `obj_type` (`obj_type`),
  KEY `obj_channel` (`obj_channel`),
  KEY `obj_obj` (`obj_obj`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}