<?php

namespace Zotlabs\Update;

class _1016 {
function run() {

	$r = q("CREATE TABLE IF NOT EXISTS `menu` (
  `menu_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `menu_channel_id` int(10) unsigned NOT NULL DEFAULT '0',
  `menu_desc` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`menu_id`),
  KEY `menu_channel_id` (`menu_channel_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	$r2 = q("CREATE TABLE IF NOT EXISTS `menu_item` (
  `mitem_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mitem_link` char(255) NOT NULL DEFAULT '',
  `mitem_desc` char(255) NOT NULL DEFAULT '',
  `allow_cid` mediumtext NOT NULL,
  `allow_gid` mediumtext NOT NULL,
  `deny_cid` mediumtext NOT NULL,
  `deny_gid` mediumtext NOT NULL,
  `mitem_channel_id` int(10) unsigned NOT NULL,
  `mitem_menu_id` int(10) unsigned NOT NULL DEFAULT '0',
  `mitem_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`mitem_id`),
  KEY `mitem_channel_id` (`mitem_channel_id`),
  KEY `mitem_menu_id` (`mitem_menu_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");


	if($r && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}