<?php

namespace Zotlabs\Update;

class _1175 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("ALTER TABLE item CHANGE `object` `obj` text NOT NULL");
		$r2 = q("ALTER TABLE photo CHANGE `size` `filesize` bigint NOT NULL DEFAULT '0' ");
		$r3 = q("ALTER TABLE photo CHANGE `scale` `imgscale` numeric(3) NOT NULL DEFAULT '0' ");
		$r4 = q("ALTER TABLE photo CHANGE `type` `mimetype` varchar(128) NOT NULL DEFAULT 'image/jpeg' ");

	}
	else {
		$r1 = q("ALTER TABLE item CHANGE `object` `obj` text NOT NULL");
		$r2 = q("ALTER TABLE photo CHANGE `size` `filesize` int(10) unsigned NOT NULL DEFAULT '0' ");
		$r3 = q("ALTER TABLE photo CHANGE `scale` `imgscale` tinyint(3) unsigned NOT NULL DEFAULT '0' ");
		$r4 = q("ALTER TABLE photo CHANGE `type` `mimetype` char(128) NOT NULL DEFAULT 'image/jpeg' ");

	}

	if($r1 && $r2 && $r3 && $r4)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}



}