<?php

namespace Zotlabs\Update;

class _1187 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("alter table outq add outq_scheduled timestamp not null default '0001-01-01 00:00:00' ");
	}
	else {
		$r1 = q("alter table outq add outq_scheduled datetime not null default '0001-01-01 00:00:00' ");
	}
	$r2 = q("create index outq_scheduled_idx on outq (outq_scheduled)");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;


}


}