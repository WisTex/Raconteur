<?php

namespace Zotlabs\Update;

class _1142 {
function run() {

	$r1 = q("alter table site add site_dead smallint not null default '0' ");
	$r2 = q("create index site_dead on site ( site_dead ) ");
	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;


}


}