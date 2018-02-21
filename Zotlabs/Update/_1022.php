<?php

namespace Zotlabs\Update;

class _1022 {
function run() {
	$r = q("alter table attach add index ( filename ), add index ( filetype ), add index ( filesize ), add index ( created ), add index ( edited ) ");
	if($r)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;
}


}