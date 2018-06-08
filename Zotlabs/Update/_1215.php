<?php

namespace Zotlabs\Update;

class _1215 {

	function run() {
		q("START TRANSACTION");

		// this will fix mastodon hubloc_url
		$r1 = q("UPDATE hubloc SET hubloc_url = LEFT(hubloc_url, POSITION('/users' IN hubloc_url)-1) WHERE POSITION('/users' IN hubloc_url)>0");

		// this will fix peertube hubloc_url
		$r2 = q("UPDATE hubloc SET hubloc_url = LEFT(hubloc_url, POSITION('/account' IN hubloc_url)-1) WHERE POSITION('/account' IN hubloc_url)>0");

		if($r1 && $r2) {
			q("COMMIT");
			return UPDATE_SUCCESS;
		}
		else {        
			q("ROLLBACK");
			return UPDATE_FAILED;
		}
	}

}
