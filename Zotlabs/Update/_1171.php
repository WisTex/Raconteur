<?php

namespace Zotlabs\Update;

class _1171 {
function run() {

		$r1 = q("ALTER TABLE verify CHANGE `type` `vtype` varchar(32) NOT NULL DEFAULT '' ");
		$r2 = q("ALTER TABLE tokens CHANGE `scope` `auth_scope` varchar(512) NOT NULL DEFAULT '' ");
		$r3 = q("ALTER TABLE auth_codes CHANGE `scope` `auth_scope` varchar(512) NOT NULL DEFAULT '' ");
		$r4 = q("ALTER TABLE clients CHANGE `name` `clname` TEXT ");
		$r5 = q("ALTER TABLE session CHANGE `data` `sess_data` TEXT NOT NULL ");
		$r6 = q("ALTER TABLE register CHANGE `language` `lang` varchar(16) NOT NULL DEFAULT '' ");

	if($r1 && $r2 && $r3 && $r4 && $r5 && $r6)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;



}


}