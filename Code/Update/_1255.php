<?php

namespace Code\Update;

use Code\Lib\PConfig;

class _1255
{

    public function run()
    {
        $r = q("SELECT * from channel where true");
        if ($r) {
            foreach ($r as $rv) {
                PConfig::Set($rv['channel_id'], 'perm_limits', 'deliver_stream', PERMS_SPECIFIC);
            }
        }
        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }
}