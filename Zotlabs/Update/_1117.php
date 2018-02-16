<?php

namespace Zotlabs\Update;

class _1117 {
function run() {
	$r = q("ALTER TABLE `channel` CHANGE `channel_a_bookmark` `channel_w_like` INT( 10 ) UNSIGNED NOT NULL DEFAULT '128',
DROP INDEX `channel_a_bookmark` , ADD INDEX `channel_w_like` ( `channel_w_like` ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}