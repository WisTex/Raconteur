<?php

namespace Code\Update;

use Code\Lib\Apps;

class _1258
{
    public function run()
    {
        q("update app set app_photo = 'icon:list-alt' where app_system = 1 and app_photo = 'icon:th'");

        return UPDATE_SUCCESS;
    }

    public function verify() {
        return true;
    }
}