<?php

namespace Code\Update;

use Code\Lib\AbConfig;

class _1257
{

    public function run()
    {
        $r = q("SELECT * from abook where abook_self = 0");
        if ($r) {
            foreach ($r as $rv) {
                $perms = AbConfig::Get($rv['abook_channel'], $rv['abook_xchan'], 'system', 'my_perms', [] );
                $s = explode(',', $perms);
                if (in_array('view_stream', $s) && (! in_array('deliver_stream', $s))) {
                    $s[] = 'deliver_stream';
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