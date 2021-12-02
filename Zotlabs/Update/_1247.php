<?php

namespace Zotlabs\Update;

class _1247
{

    public function run()
    {
        // remove deprecated apps from system list
        $access = 'a2e9cee1a71e8b82f662d131a7cda1606b84b9be283715c967544e19cb34dd0821b65580c942ca38d7620638a44f26034536597a2c3a5c969e2dbaedfcd1d282';

        q("delete from app where app_id = '$access' ");
        return UPDATE_SUCCESS;

    }

    public function verify()
    {
        return true;
    }

}
