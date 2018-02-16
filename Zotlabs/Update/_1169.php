<?php

namespace Zotlabs\Update;

class _1169 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("ALTER TABLE `addon` CHANGE `timestamp` `tstamp` numeric( 20 ) UNSIGNED NOT NULL DEFAULT '0' ");
		$r2 = q("ALTER TABLE `addon` CHANGE `name` `aname` text NOT NULL DEFAULT '' ");
		$r3 = q("ALTER TABLE `hook` CHANGE `function` `fn` text NOT NULL DEFAULT '' ");

	}
	else {
		$r1 = q("ALTER TABLE `addon` CHANGE `timestamp` `tstamp` BIGINT( 20 ) UNSIGNED NOT NULL DEFAULT '0' ");
		$r2 = q("ALTER TABLE `addon` CHANGE `name` `aname` CHAR(255) NOT NULL DEFAULT '' ");
		$r3 = q("ALTER TABLE `hook` CHANGE `function` `fn` CHAR(255) NOT NULL DEFAULT '' ");
	}

	if($r1 && $r2 && $r3)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}