<?php

namespace Code\Update;

class _1118
{
    public function run()
    {
        $r = q("ALTER TABLE `account` ADD `account_password_changed` DATETIME NOT NULL DEFAULT '0001-01-01 00:00:00',
ADD INDEX ( `account_password_changed` )");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
