<?php

namespace Zotlabs\Update;

class _1151 {
function run() {

	$r3 = q("select likes.*, item.mid from likes left join item on likes.iid = item.id");
	if($r3) {
		foreach($r3 as $rr) {
			q("update likes set i_mid = '%s' where id = $d",
				dbesc($rr['mid']),
				intval($rr['id'])
			);
		}
	}


	return UPDATE_SUCCESS;

}


}