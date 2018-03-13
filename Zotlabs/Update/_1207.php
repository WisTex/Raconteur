<?php

namespace Zotlabs\Update;

class _1207 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
			$r = q("ALTER TABLE item 
				DROP INDEX resource_type
			");

			if($r)
				return UPDATE_SUCCESS;
			return UPDATE_FAILED;
		}
		else {
			return UPDATE_SUCCESS;
		}

	}

}
