<?php

namespace Zotlabs\Update;

class _1102 {
function run() {
	$r = q("update abook set abook_flags = (abook_flags - %d)
		where ( abook_flags & %d)",
		intval(ABOOK_FLAG_UNCONNECTED),
		intval(ABOOK_FLAG_UNCONNECTED)
	);
	return UPDATE_SUCCESS;
}


}