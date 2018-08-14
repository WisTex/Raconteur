<?php

namespace Zotlabs\Update;

class _1217 {

	function run() {
		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r = q("ALTER TABLE app ADD app_options smallint NOT NULL DEFAULT '0' ");
		}
		else {
			$r = q("ALTER TABLE app ADD app_options int(11) NOT NULL DEFAULT 0 ");
			
		}

		if($r) {
			return UPDATE_SUCCESS;
		}
		return UPDATE_FAILED;
	}
}

