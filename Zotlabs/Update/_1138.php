<?php

namespace Zotlabs\Update;

class _1138 {
function run() {
	$r1 = q("alter table outq add outq_priority smallint not null default '0' ");
	$r2 = q("create index outq_priority on outq ( outq_priority ) ");
	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}