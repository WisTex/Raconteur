<?php

namespace Zotlabs\Update;

class _1146 {
function run() {

	$r1 = q("alter table event add event_sequence smallint not null default '0' ");
	$r2 = q("create index event_sequence on event ( event_sequence ) ");
	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}