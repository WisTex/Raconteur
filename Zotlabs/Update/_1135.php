<?php

namespace Zotlabs\Update;

class _1135 {
function run() {
	$r = q("ALTER TABLE xlink ADD xlink_sig TEXT NOT NULL DEFAULT ''");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}