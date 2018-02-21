<?php

namespace Zotlabs\Update;

class _1149 {
function run() {
	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) { 
		$r1 = q("ALTER TABLE obj ADD obj_term CHAR( 255 ) NOT NULL DEFAULT '',
			ADD obj_url CHAR( 255 ) NOT NULL DEFAULT '',
			ADD obj_imgurl CHAR( 255 ) NOT NULL DEFAULT '',
			ADD obj_created timestamp NOT NULL DEFAULT '0001-01-01 00:00:00',
			ADD obj_edited timestamp NOT NULL DEFAULT '0001-01-01 00:00:00' ");
	}
	else {
		$r1 = q("ALTER TABLE obj ADD obj_term CHAR( 255 ) NOT NULL DEFAULT '',
			ADD obj_url CHAR( 255 ) NOT NULL DEFAULT '',
			ADD obj_imgurl CHAR( 255 ) NOT NULL DEFAULT '',
			ADD obj_created DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
			ADD obj_edited DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00' ");
	}

	$r2 = q("create index obj_term on obj ( obj_term ) ");
	$r3 = q("create index obj_url on obj ( obj_url ) ");
	$r4 = q("create index obj_imgurl on obj ( obj_imgurl ) ");
	$r5 = q("create index obj_created on obj ( obj_created ) ");
	$r6 = q("create index obj_edited on obj ( obj_edited ) ");
	$r = $r1 && $r2 && $r3 && $r4 && $r5 && $r6;
    if($r)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;

}


}