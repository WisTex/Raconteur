<?php

namespace Zotlabs\Update;

class _1190 {
function run() {
	$r1 = q("alter table abook add abook_not_here smallint not null default 0 ");

	$r2 = q("create index abook_not_here on abook (abook_not_here)");

	if($r1 && $r2)
		return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}


}