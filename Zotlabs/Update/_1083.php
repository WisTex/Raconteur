<?php

namespace Zotlabs\Update;

class _1083 {
function run() {
	$r = q("ALTER TABLE `notify` ADD `aid` INT NOT NULL AFTER `msg` ,
ADD INDEX ( `aid` )");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}