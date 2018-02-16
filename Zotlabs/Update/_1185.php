<?php

namespace Zotlabs\Update;

class _1185 {
function run() {

	$r1 = q("alter table app add app_plugin text not null default '' ");

	if($r1)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}