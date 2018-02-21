<?php

namespace Zotlabs\Update;

class _1137 {
function run() {
	$r1 = q("alter table site add site_valid smallint not null default '0' ");
	$r2 = q("create index site_valid on site ( site_valid ) ");
	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}