<?php

namespace Zotlabs\Update;

class _1208 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = q("ALTER TABLE poll ADD poll_author text NOT NULL");
 			$r2 = q("create index \"poll_author_idx\" on poll (\"poll_author\") ");

			$r = ($r1 && $r2);
		}
		else {
			$r = q("ALTER TABLE `poll` ADD `poll_author` VARCHAR(191) NOT NULL AFTER `poll_votes`, 
				ADD INDEX `poll_author` (`poll_author`)");
		}

		if($r)
			return UPDATE_SUCCESS;
		return UPDATE_FAILED;

	}

}
