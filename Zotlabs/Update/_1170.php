<?php

namespace Zotlabs\Update;

class _1170 {
function run() {

	$r1 = q("drop table fcontact");	
	$r2 = q("drop table ffinder");	
	$r3 = q("drop table fserver");	
	$r4 = q("drop table fsuggest");	
	$r5 = q("drop table spam");	

	if($r1 && $r2 && $r3 && $r4 && $r5)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}