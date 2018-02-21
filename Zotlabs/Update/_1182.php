<?php

namespace Zotlabs\Update;

class _1182 {
function run() {

	$r1 = q("alter table site add site_version varchar(32) not null default '' ");

	if($r1)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}



}