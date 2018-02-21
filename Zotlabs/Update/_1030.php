<?php

namespace Zotlabs\Update;

class _1030 {
function run() {
	$r = q("CREATE TABLE IF NOT EXISTS `issue` (
`issue_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`issue_created` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
`issue_updated` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
`issue_assigned` CHAR( 255 ) NOT NULL ,
`issue_priority` INT NOT NULL ,
`issue_status` INT NOT NULL ,
`issue_component` CHAR( 255 ) NOT NULL,
KEY `issue_created` (`issue_created`),
KEY `issue_updated` (`issue_updated`),
KEY `issue_assigned` (`issue_assigned`),
KEY `issue_priority` (`issue_priority`),
KEY `issue_status` (`issue_status`),
KEY `issue_component` (`issue_component`)
) ENGINE = MYISAM DEFAULT CHARSET=utf8");

	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}