<?php

namespace Zotlabs\Update;

class _1158 {
function run() {
	$r = q("select attach.id, attach.data, channel_address from attach left join channel on attach.uid = channel_id where os_storage = 1 and not attach.data like '%%store%%' ");
	if($r) {
		foreach($r as $rr) {
			$has_slash = ((substr($rr['data'],0,1) === '/') ? true : false);
			q("update attach set data = '%s' where id = %d",
				dbesc('store/' . $rr['channel_address']. (($has_slash) ? '' : '/' . $rr['data'])),
				dbesc($rr['id'])
			);
		}
	}
	return UPDATE_SUCCESS;
}



}