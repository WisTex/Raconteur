<?php

namespace Zotlabs\Update;

class _1109 {
function run() {
	$r = q("ALTER TABLE `app` CHANGE `app_id` `app_id` CHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}

// We ended up with an extra zero in the name for 1108, so do it over and ignore the result.


}