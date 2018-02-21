<?php

namespace Zotlabs\Update;

class _1155 {
function run() {

	$r1 = q("alter table site add site_type smallint not null default '0' ");
	$r2 = q("create index site_type on site ( site_type ) ");
	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}