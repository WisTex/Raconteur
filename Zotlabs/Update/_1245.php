<?php

namespace Zotlabs\Update;

class _1245
{

    public function run()
    {

        q("delete from app where app_url like '%%/nocomment'");
        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }
}
