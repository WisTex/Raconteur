<?php

namespace Zotlabs\Update;

class _1136 {
function run() {
	$r1 = q("alter table item add item_unseen smallint not null default '0' ");
	$r2 = q("create index item_unseen on item ( item_unseen ) ");
	$r3 = q("update item set item_unseen = 1 where ( item_flags & 2 ) > 0 ");

	if($r1 && $r2 && $r3)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}