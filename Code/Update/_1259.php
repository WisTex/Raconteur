<?php

namespace Code\Update;

class _1259
{

    public function run()
    {
        // remove deprecated apps from system list
        $access = 'd82fcd79afd8783e4d493cc6fda94081295938b1ae68d7a760610c08abac124b389f37081fda7a928b3d9e864984010c9a8b61390b179a280019c74bd1d41896';

        q("delete from app where app_id = '$access' ");
        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }
}
