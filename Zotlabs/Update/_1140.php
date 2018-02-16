<?php

namespace Zotlabs\Update;

class _1140 {
function run() {
	$r = q("select * from clients where true");
	$x = false;
	if($r) {
		foreach($r as $rr) {
			$m = q("INSERT INTO xperm (xp_client, xp_channel, xp_perm) VALUES ('%s', %d, '%s') ",
				dbesc($rr['client_id']),
				intval($rr['uid']),
				dbesc('all')
			);
			if(! $m)
				$x = true;
		}
	}
	if($x)
		return UPDATE_FAILED;
	return UPDATE_SUCCESS;
}



}