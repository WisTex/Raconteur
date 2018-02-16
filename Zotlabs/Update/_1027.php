<?php

namespace Zotlabs\Update;

class _1027 {
function run() {
	$r = q("ALTER TABLE `abook` ADD `abook_rating` INT NOT NULL DEFAULT '0' AFTER `abook_closeness` ,
ADD INDEX ( `abook_rating` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}