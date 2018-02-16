<?php

namespace Zotlabs\Update;

class _1188 {
function run() {

	$r1 = q("alter table channel add channel_password varchar(255) not null default '' ");
	$r2 = q("alter table channel add channel_salt varchar(255) not null default '' ");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}