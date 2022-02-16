<?php

namespace Code\Update;

class _1081
{
    public function run()
    {
        $r = q("DROP TABLE `queue` ");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
