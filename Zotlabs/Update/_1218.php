<?php

namespace Zotlabs\Update;

class _1218 {

	function run() {

		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = q("ALTER TABLE hubloc add hubloc_id_url text NOT NULL DEFAULT ''");
			$r2 = q("create index \"hubloc_id_url\" on hubloc (\"hubloc_id_url\")");
			$r3 = q("ALTER TABLE hubloc add hubloc_site_id text NOT NULL DEFAULT ''");
			$r4 = q("create index \"hubloc_site_id\" on hubloc (\"hubloc_site_id\")");

			$r = $r1 && $r2 && $r3 && $r4;
		}

		if(ACTIVE_DBTYPE == DBTYPE_MYSQL) {
			$r1 = q("ALTER TABLE hubloc add hubloc_id_url varchar(191) NOT NULL, ADD INDEX hubloc_id_url (hubloc_id_url)");
			$r2 = q("ALTER TABLE hubloc add hubloc_site_id varchar(191) NOT NULL, ADD INDEX hubloc_site_id (hubloc_site_id)");

			$r = $r1 && $r2;
		}

		if($r)
			return UPDATE_SUCCESS;
		return UPDATE_FAILED;

	}

}
