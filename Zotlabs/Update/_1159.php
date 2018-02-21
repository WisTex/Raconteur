<?php

namespace Zotlabs\Update;

class _1159 {
function run() {
	$r = q("select attach.id, attach.data, attach.hash, channel_address from attach left join channel on attach.uid = channel_id where os_storage = 1 ");
	if($r) {
		foreach($r as $rr) {
			$x = dbunescbin($rr['data']);
			$has_slash = (($x === 'store/' . $rr['channel_address'] . '/') ? true : false); 
			if(($x === 'store/' . $rr['channel_address']) || ($has_slash)) {
				q("update attach set data = '%s' where id = %d",
					dbesc('store/' . $rr['channel_address']. (($has_slash) ? '' : '/' . $rr['hash'])),
					dbesc($rr['id'])
				);
			}
		}
	}
	return UPDATE_SUCCESS;
}



}