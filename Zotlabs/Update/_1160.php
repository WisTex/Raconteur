<?php

namespace Zotlabs\Update;

class _1160 {
function run() {
	$r = q("alter table abook add abook_instance text not null default '' ");
	if($r)
		return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}


}