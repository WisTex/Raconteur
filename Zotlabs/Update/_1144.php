<?php

namespace Zotlabs\Update;

class _1144 {
function run() {
	$r = q("select flags, id from attach where flags != 0");
	if($r) {
		foreach($r as $rr) {
			if($rr['flags'] & 1) {
				q("update attach set is_dir = 1 where id = %d",
					intval($rr['id'])
				);
			}
			if($rr['flags'] & 2) {
				q("update attach set os_storage = 1 where id = %d",
					intval($rr['id'])
				);
			}
		}
	}

	return UPDATE_SUCCESS;
}


}