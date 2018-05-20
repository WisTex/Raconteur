<?php

namespace Zotlabs\Update;

class _1213 {

	function run() {
		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
			q("START TRANSACTION");

			$r = q("ALTER TABLE abconfig 
				DROP INDEX chan,
				DROP INDEX xchan,
				ADD INDEX chan_xchan (chan, xchan)
			");

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

}
