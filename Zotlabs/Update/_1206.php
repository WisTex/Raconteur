<?php

namespace Zotlabs\Update;

class _1206 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
			$r = q("ALTER TABLE item 
				ADD INDEX uid_resource_type (uid, resource_type)
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
