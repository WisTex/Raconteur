<?php

namespace Code\Update;

class _1221
{

    public function run()
    {

        $r1 = q("ALTER table " . TQUOT . 'groups' . TQUOT . " rename to pgrp ");
        $r2 = q("ALTER table " . TQUOT . 'group_member' . TQUOT . " rename to pgrp_member ");


        if ($r1 && $r2) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
