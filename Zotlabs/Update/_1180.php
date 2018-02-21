<?php

namespace Zotlabs\Update;

class _1180 {
function run() {

	require_once('include/perm_upgrade.php');

	$r1 = q("select * from channel where true");
	if($r1) {
		foreach($r1 as $rr) {
			perm_limits_upgrade($rr);
			autoperms_upgrade($rr);
		}
	}

	$r2 = q("select * from abook where true");
	if($r2) {
		foreach($r2 as $rr) {
			perm_abook_upgrade($rr);
		}
	}
	
	$r = $r1 && $r2;
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}