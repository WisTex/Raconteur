<?php

namespace Zotlabs\Update;

class _1209 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = q("ALTER TABLE poll_elm ADD pelm_order numeric(6) NOT NULL DEFAULT '0' ");
 			$r2 = q("create index \"pelm_order_idx\" on poll_elm (\"pelm_order\")");

			$r = ($r1 && $r2);
		}
		else {
			$r = q("ALTER TABLE `poll_elm` ADD `pelm_order` int(11) NOT NULL DEFAULT 0, 
				ADD INDEX `pelm_order` (`pelm_order`)");
		}

		if($r)
			return UPDATE_SUCCESS;
		return UPDATE_FAILED;

	}

}
