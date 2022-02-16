<?php

namespace Code\Update;

class _1224
{

    public function run()
    {
        q("update abook set abook_closeness = 80 where abook_closeness = 0 and abook_self = 0");
        return UPDATE_SUCCESS;
    }
}
