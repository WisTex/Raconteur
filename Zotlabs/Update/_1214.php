<?php

namespace Zotlabs\Update;

class _1214 {

	function run() {
		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			q("START TRANSACTION");

			$r1 = q("ALTER TABLE oauth_clients ALTER COLUMN user_id TYPE bigint USING user_id::bigint");
			$r2 = q("ALTER TABLE oauth_clients ALTER COLUMN user_id SET NOT NULL");
			$r3 = q("ALTER TABLE oauth_clients ALTER COLUMN user_id SET DEFAULT 0");

			$r4 = q("ALTER TABLE oauth_access_tokens ALTER COLUMN user_id TYPE bigint USING user_id::bigint");
			$r5 = q("ALTER TABLE oauth_access_tokens ALTER COLUMN user_id SET NOT NULL");
			$r6 = q("ALTER TABLE oauth_access_tokens ALTER COLUMN user_id SET DEFAULT 0");

			$r7 = q("ALTER TABLE oauth_authorization_codes ALTER COLUMN user_id TYPE bigint USING user_id::bigint");
			$r8 = q("ALTER TABLE oauth_authorization_codes ALTER COLUMN user_id SET NOT NULL");
			$r9 = q("ALTER TABLE oauth_authorization_codes ALTER COLUMN user_id SET DEFAULT 0");

			$r10 = q("ALTER TABLE oauth_refresh_tokens ALTER COLUMN user_id TYPE bigint USING user_id::bigint");
			$r11 = q("ALTER TABLE oauth_refresh_tokens ALTER COLUMN user_id SET NOT NULL");
			$r12 = q("ALTER TABLE oauth_refresh_tokens ALTER COLUMN user_id SET DEFAULT 0");


			if($r1 && $r2 && $r3 && $r4 && $r5 && $r6 && $r7 && $r8 && $r9 && $r10 && $r11 && $r12) {
				q("COMMIT");
				return UPDATE_SUCCESS;
			}
			else {        
				q("ROLLBACK");
				return UPDATE_FAILED;
			}
		}
		else {
			q("START TRANSACTION");

			$r1 = q("ALTER TABLE oauth_clients MODIFY COLUMN user_id int(10) unsigned NOT NULL DEFAULT 0");
			$r2 = q("ALTER TABLE oauth_access_tokens MODIFY COLUMN user_id int(10) unsigned NOT NULL DEFAULT 0");
			$r3 = q("ALTER TABLE oauth_authorization_codes MODIFY COLUMN user_id int(10) unsigned NOT NULL DEFAULT 0");
			$r4 = q("ALTER TABLE oauth_refresh_tokens MODIFY COLUMN user_id int(10) unsigned NOT NULL DEFAULT 0");

			if($r1 && $r2 && $r3 && $r4) {
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
