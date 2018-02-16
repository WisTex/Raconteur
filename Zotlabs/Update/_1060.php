<?php

namespace Zotlabs\Update;

class _1060 {
function run() {

	$r = q("CREATE TABLE IF NOT EXISTS `vote` (
  `vote_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vote_poll` int(11) NOT NULL DEFAULT '0',
  `vote_element` int(11) NOT NULL DEFAULT '0',
  `vote_result` text NOT NULL,
  `vote_xchan` char(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`vote_id`),
  UNIQUE KEY `vote_vote` (`vote_poll`,`vote_element`,`vote_xchan`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}