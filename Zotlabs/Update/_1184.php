<?php

namespace Zotlabs\Update;

class _1184 {
function run() {

	$r1 = q("alter table site add site_crypto text not null default '' ");

	if($r1)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}