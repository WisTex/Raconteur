<?php

namespace Zotlabs\Update;

class _1045 {
function run() {
	$r = q("ALTER TABLE `site` ADD `site_register` INT NOT NULL DEFAULT '0',
ADD INDEX ( `site_register` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}
	

}