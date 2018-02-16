<?php

namespace Zotlabs\Update;

class _1019 {
function run() {
	$r = q("ALTER TABLE `event` DROP `message_id` ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}