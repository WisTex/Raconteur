<?php

namespace Zotlabs\Update;

class _1156 {
function run() {
	$r1 = q("ALTER TABLE mail ADD conv_guid CHAR( 255 ) NOT NULL DEFAULT '' ");
	$r2 = q("create index conv_guid on mail ( conv_guid ) ");

	$r3 = q("select mail.id, mail.convid, conv.guid from mail left join conv on mail.convid = conv.id where true");
	if($r3) {
		foreach($r3 as $rr) {
			if($rr['convid']) {
				q("update mail set conv_guid = '%s' where id = %d",
					dbesc($rr['guid']),
					intval($rr['id'])
				);
			}
		}
	}
		
    if($r1 && $r2)
        return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}