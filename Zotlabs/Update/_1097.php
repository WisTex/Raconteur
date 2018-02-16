<?php

namespace Zotlabs\Update;

class _1097 {
function run() {

	// fix some mangled hublocs from a bug long ago

	$r = q("select hubloc_id, hubloc_addr from hubloc where hubloc_addr like '%%/%%'");
	if($r) {
		foreach($r as $rr) {
			q("update hubloc set hubloc_addr = '%s' where hubloc_id = %d",
				dbesc(substr($rr['hubloc_addr'],0,strpos($rr['hubloc_addr'],'/'))),
				intval($rr['hubloc_id'])
			);
		}
	}
	return UPDATE_SUCCESS;
	
}


}