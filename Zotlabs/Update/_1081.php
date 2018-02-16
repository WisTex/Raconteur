<?php

namespace Zotlabs\Update;

class _1081 {
function run() {
	$r = q("DROP TABLE `queue` ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}