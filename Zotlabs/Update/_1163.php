<?php

namespace Zotlabs\Update;

class _1163 {
function run() {

	if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
		$r1 = q("alter table channel add channel_moved text not null default '' ");
		$r2 = q("create index \"channel_channel_moved\" on channel (\"channel_moved\") ");
	} 
	else {
		$r1 = q("alter table channel add channel_moved char(255) not null default '' ");
		$r2 = q("alter table channel add index ( channel_moved ) ");
	}
    if($r1 && $r2)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}


}