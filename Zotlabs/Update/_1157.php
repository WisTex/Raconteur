<?php

namespace Zotlabs\Update;

class _1157 {
function run() {
	$r1 = q("alter table site add site_project char(255) not null default '' ");
    $r2 = q("create index site_project on site ( site_project ) ");
    if($r1 && $r2)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;

}



}