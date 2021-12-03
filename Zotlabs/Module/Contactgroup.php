<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\AccessList;

/*
 * Adds or removes a connection from an AccessList
 * If the connection is already a member, they are removed.
 * If they are not a member, they are added.
 *
 * argv[1] => AccessList['id']
 * argv[2] => connection portable_id (base64url_encoded for transport)
 *
 */


class Contactgroup extends Controller
{

    public function get()
    {

        if (!local_channel()) {
            killme();
        }

        if ((argc() > 2) && (intval(argv(1))) && (argv(2))) {
            $r = abook_by_hash(local_channel(), base64url_decode(argv(2)));
            if ($r) {
                $change = $r['abook_xchan'];
            }
        }

        if ((argc() > 1) && (intval(argv(1)))) {
            $group = AccessList::by_id(local_channel(), argv(1));

            if (!$group) {
                killme();
            }

            $members = AccessList::members(local_channel(), $group['id']);
            $preselected = ids_to_array($members, 'xchan_hash');

            if ($change) {
                if (in_array($change, $preselected)) {
                    AccessList::member_remove(local_channel(), $group['gname'], $change);
                } else {
                    AccessList::member_add(local_channel(), $group['gname'], $change);
                }
            }
        }

        killme();
    }
}
