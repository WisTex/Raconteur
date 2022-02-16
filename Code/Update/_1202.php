<?php

namespace Code\Update;

class _1202
{

    public function run()
    {

        // empty update in order to make the DB_UPDATE_VERSION equal to the current maximum update function
        // rather than being one greater than the last known update

        return UPDATE_SUCCESS;
    }
}
