<?php

namespace Zotlabs\Update;

class _1068 {
function run(){
        $r = q("ALTER TABLE `hubloc` ADD `hubloc_status` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `hubloc_flags` , ADD INDEX ( `hubloc_status` )");
        if($r)
                return UPDATE_SUCCESS;
        return UPDATE_FAILED;
}


}