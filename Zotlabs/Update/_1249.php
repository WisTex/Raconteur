<?php

namespace Zotlabs\Update;

use Zotlabs\Access\Permissions;

class _1249
{

    public function run()
    {

        $channels = [];

        $r = q("select * from abook where abook_self = 0");
        if ($r) {
            foreach ($r as $rv) {
                if (!in_array(intval($rv['abook_channel']), $channels)) {
                    set_pconfig($rv['abook_channel'], 'perm_limits', 'post_mail', PERMS_SPECIFIC);
                    $channels[] = intval($rv['abook_channel']);
                }

                $x = get_abconfig($rv['abook_channel'], $rv['abook_xchan'], 'system', 'my_perms');
                if ($x) {
                    $y = explode(',', $x);
                    if ($y && in_array('post_comments', $y) && !in_array('post_mail', $y)) {
                        $y[] = 'post_mail';
                        set_abconfig($rv['abook_channel'], $rv['abook_xchan'], 'system', 'my_perms', implode(',', $y));
                    }
                }
            }
        }

        return UPDATE_SUCCESS;
    }

    public function verify()
    {
        return true;
    }

}
