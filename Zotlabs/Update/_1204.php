<?php

namespace Zotlabs\Update;

class _1204 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = q("ALTER TABLE poll ADD poll_guid text NOT NULL");
			$r2 = q("create index \"poll_guid_idx\" on poll (\"poll_guid\")");
			$r3 = q("ALTER TABLE poll_elm ADD pelm_guid text NOT NULL");
			$r4 = q("create index \"pelm_guid_idx\" on poll_elm (\"pelm_guid\")");
			$r5 = q("ALTER TABLE vote ADD vote_guid text NOT NULL");
			$r6 = q("create index \"vote_guid_idx\" on vote (\"vote_guid\")");

			$r = ($r1 && $r2 && $r3 && $r4 && $r5 && $r6);
		}
		else {
			$r1 = q("ALTER TABLE `poll` ADD `poll_guid` VARCHAR(191) NOT NULL, ADD INDEX `poll_guid` (`poll_guid`) ");
			$r2 = q("ALTER TABLE `poll_elm` ADD `pelm_guid` VARCHAR(191) NOT NULL, ADD INDEX `pelm_guid` (`pelm_guid`) ");
			$r3 = q("ALTER TABLE `vote` ADD `vote_guid` VARCHAR(191) NOT NULL, ADD INDEX `vote_guid` (`vote_guid`) ");

			$r = ($r1 && $r2 && $r3);
		}
			
		if($r)
			return UPDATE_SUCCESS;

		return UPDATE_FAILED;

	}

}
