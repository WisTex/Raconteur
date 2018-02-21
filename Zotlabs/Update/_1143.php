<?php

namespace Zotlabs\Update;

class _1143 {
function run() {

	$r1 = q("ALTER TABLE abook ADD abook_incl TEXT NOT NULL DEFAULT ''");
	$r2 = q("ALTER TABLE abook ADD abook_excl TEXT NOT NULL DEFAULT '' ");
	if($r1 && $r2)
		return UPDATE_SUCCESS;
	return UPDATE_FAILED;

}


}