<?php

namespace Code\Update;

use Code\Lib\AbConfig;
use Code\Lib\PConfig;

class _1266
{

    // This update adds the 'search_stream' permission to existing connections.
    public function run()
    {
        $r = q("SELECT * from channel where true");
        if ($r) {
            foreach ($r as $rv) {
                PConfig::Set($rv['channel_id'], 'perm_limits', 'search_stream', PERMS_SPECIFIC);
            }
        }
        $r = q("SELECT * from abook where abook_self = 0");
        if ($r) {
            foreach ($r as $rv) {
                $perms = AbConfig::Get($rv['abook_channel'], $rv['abook_xchan'], 'system', 'my_perms', '' );
                $s = explode(',', $perms);
                if (in_array('view_stream', $s) && (! in_array('search_stream', $s))) {
                    $s[] = 'search_stream';
                }
                AbConfig::Set($rv['abook_channel'], $rv['abook_xchan'], 'system', 'my_perms', implode(',', $s));
            }
        }

        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }
}
