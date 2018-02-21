<?php

namespace Zotlabs\Update;

class _1154 {
function run() {

	$r = q("ALTER TABLE event ADD event_vdata text NOT NULL ");
    if($r)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;

}



}