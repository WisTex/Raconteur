<?php

namespace Zotlabs\Update;

class _1178 {
function run() {

	$c2 = null;

	$c1 = q("SELECT channel_id, channel_hash from channel where true");
	if($c1) {
		$c2 = q("SELECT id, chan from abconfig where true");
		if($c2) {
			for($x = 0; $x < count($c2); $x ++) {
				foreach($c1 as $c) {
					if($c['channel_hash'] == $c2[$x]['chan']) {
						$c2[$x]['chan'] = $c['channel_id'];
						break;
					}
				}
			}
		}
	}

	$r1 = q("ALTER TABLE abconfig CHANGE chan chan int(10) unsigned NOT NULL DEFAULT '0' ");

	if($c2) {
		foreach($c2 as $c) {
			q("UPDATE abconfig SET chan = %d where id = %d",
				intval($c['chan']),
				intval($c['id'])
			);
		}
	}

	if($r1)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}