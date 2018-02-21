<?php

namespace Zotlabs\Update;

class _1186 {
function run() {

	$r1 = q("alter table profile add profile_vcard text not null");

	if($r1)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;


}


}