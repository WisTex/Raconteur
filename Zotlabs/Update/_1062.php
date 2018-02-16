<?php

namespace Zotlabs\Update;

class _1062 {
function run() {
	$r1 = q("CREATE TABLE IF NOT EXISTS `poll` (
`poll_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`poll_channel` INT UNSIGNED NOT NULL DEFAULT '0',
`poll_desc` TEXT NOT NULL DEFAULT '',
`poll_flags` INT NOT NULL DEFAULT '0',
`poll_votes` INT NOT NULL DEFAULT '0',
KEY `poll_channel` (`poll_channel`),
KEY `poll_flags` (`poll_flags`),
KEY `poll_votes` (`poll_votes`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	$r2 = q("CREATE TABLE IF NOT EXISTS `poll_elm` (
`pelm_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`pelm_poll` INT UNSIGNED NOT NULL DEFAULT '0',
`pelm_desc` TEXT NOT NULL DEFAULT '',
`pelm_flags` INT NOT NULL DEFAULT '0',
`pelm_result` FLOAT NOT NULL DEFAULT '0',
KEY `pelm_poll` (`pelm_poll`),
KEY `pelm_result` (`pelm_result`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}