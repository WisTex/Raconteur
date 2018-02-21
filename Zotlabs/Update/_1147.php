<?php

namespace Zotlabs\Update;

class _1147 {
function run() {

    $r1 = q("alter table event add event_priority smallint not null default '0' ");
    $r2 = q("create index event_priority on event ( event_priority ) ");
    if($r1 && $r2)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;
}


}