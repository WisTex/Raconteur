<?php

namespace Zotlabs\Update;

class _1219 {

	function run() {
		q("START TRANSACTION");

		$r = q("DELETE FROM xchan WHERE
			xchan_hash like '%s' AND
			xchan_network = 'activitypub'",
			dbesc(z_root()) . '%'
		);

		if($r) {
			q("COMMIT");
			return UPDATE_SUCCESS;
		}
		else {        
			q("ROLLBACK");
			return UPDATE_FAILED;
		}
	}

}
