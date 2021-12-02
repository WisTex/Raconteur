<?php

namespace Zotlabs\Update;

class _1250
{

    public function run()
    {
        // remove deprecated apps from system list
        $access = '067b70e92e35cc1b729c8c386bf8289cbec2618911a87c460a9b4705f2c151f8535402d468d469eeb630fad2c9cdd9aced80fb2b7cb29e47ae8f9c22c83ee7f2';

        q("delete from app where app_id = '$access' ");
        return UPDATE_SUCCESS;

    }

    public function verify()
    {
        return true;
    }

}
