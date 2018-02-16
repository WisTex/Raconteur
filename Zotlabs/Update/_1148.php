<?php

namespace Zotlabs\Update;

class _1148 {
function run() {
    $r1 = q("alter table likes add i_mid char(255) not null default '' ");
    $r2 = q("create index i_mid on likes ( i_mid ) ");

    if($r1 && $r2)
        return UPDATE_SUCCESS;
    return UPDATE_FAILED;

}


}