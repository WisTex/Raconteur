<?php

namespace Code\Update;

class _1256
{

    public function run()
    {
        // remove deprecated apps from system list
        $access = '0cf7766d38c1d2bd51093ba237a440ddf76805c23466d13e7aedb4b1c156b1fda008a73d94a45328fb6ccb8c5f1fd5648a13ac78271734096ea99a3c41608665';

        q("delete from app where app_id = '$access' ");
        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }
}
