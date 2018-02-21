<?php

namespace Zotlabs\Update;

class _1040 {
function run() {
	$r1 = q("ALTER TABLE `session` CHANGE `expire` `expire` BIGINT UNSIGNED NOT NULL ");
	$r2 = q("ALTER TABLE `tokens` CHANGE `expires` `expires` BIGINT UNSIGNED NOT NULL ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}