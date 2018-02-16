<?php

namespace Zotlabs\Update;

class _1073 {
function run() {
	$r1 = q("CREATE TABLE IF NOT EXISTS `source` (
`src_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`src_channel_id` INT UNSIGNED NOT NULL DEFAULT '0',
`src_channel_xchan` CHAR( 255 ) NOT NULL DEFAULT '',
`src_xchan` CHAR( 255 ) NOT NULL DEFAULT '',
`src_patt` MEDIUMTEXT NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ");

	$r2 = q("ALTER TABLE `source` ADD INDEX ( `src_channel_id` ), ADD INDEX ( `src_channel_xchan` ), ADD INDEX ( `src_xchan` ) ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}