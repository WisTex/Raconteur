<?php

namespace Zotlabs\Update;

class _1176 {
function run() {

	$r = q("select * from item_id where true");
	if($r) {
		foreach($r as $rr) {
			\Zotlabs\Lib\IConfig::Set($rr['iid'],'system',$rr['service'],$rr['sid'],true);
		}
	}
	return UPDATE_SUCCESS;

}


}