<?php

namespace Zotlabs\Update;

use Zotlabs\Lib\PConfig;

class _1228
{

    public function run()
    {

        $r = q("select channel_id from channel where true");
        if ($r) {
            foreach ($r as $rv) {
                PConfig::Set($rv['channel_id'], 'perm_limits', 'moderated', (string)PERMS_SPECIFIC);
            }
        }

        return UPDATE_SUCCESS;

    }

}
