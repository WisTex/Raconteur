<?php

namespace Zotlabs\Update;

class _1153 {
function run() {

	$r1 = q("ALTER TABLE dreport ADD dreport_queue CHAR( 255 ) NOT NULL DEFAULT '' ");
	$r2 = q("create index dreport_queue on dreport ( dreport_queue) ");
    if($r1 && $r2)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;


}


}