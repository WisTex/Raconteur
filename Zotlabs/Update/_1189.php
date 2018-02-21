<?php

namespace Zotlabs\Update;

class _1189 {
function run() {

	$r1 = q("alter table mail add mail_mimetype varchar(64) not null default 'text/bbcode' ");

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r2 = q("alter table mail add mail_raw smallint not null default 0 ");
	}
	else {
		$r2 = q("alter table mail add mail_raw tinyint(4) not null default 0 ");
	}
	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}