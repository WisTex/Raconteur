<?php

namespace Zotlabs\Update;

class _1052 {
function run() {
	$r = q("ALTER TABLE `channel` ADD UNIQUE (`channel_address`) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}