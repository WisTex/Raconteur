<?php

namespace Code\Module;

use Code\Web\Controller;

require_once('include/security.php');

class Lockview extends Controller
{

    public function get()
    {

        $atokens = [];

        if (local_channel()) {
            $at = q(
                "select * from atoken where atoken_uid = %d",
                intval(local_channel())
            );
            if ($at) {
                foreach ($at as $t) {
                    $atokens[] = atoken_xchan($t);
                }
            }
        }

        $type = ((argc() > 1) ? argv(1) : 0);
        if (is_numeric($type)) {
            $item_id = intval($type);
            $type = 'item';
        } else {
            $item_id = ((argc() > 2) ? intval(argv(2)) : 0);
        }

        if (!$item_id) {
            killme();
        }

        if (!in_array($type, array('item', 'photo', 'attach', 'event', 'menu_item', 'chatroom'))) {
            killme();
        }

        // we have different naming in in menu_item table and chatroom table
        switch ($type) {
            case 'menu_item':
                $id = 'mitem_id';
                break;
            case 'chatroom':
                $id = 'cr_id';
                break;
            default:
                $id = 'id';
                break;
        }

        $r = q(
            "SELECT * FROM %s WHERE $id = %d LIMIT 1",
            dbesc($type),
            intval($item_id)
        );

        if (!$r) {
            killme();
        }

        $item = $r[0];

        // we have different naming in in menu_item table and chatroom table
        switch ($type) {
            case 'menu_item':
                $uid = $item['mitem_channel_id'];
                break;
            case 'chatroom':
                $uid = $item['cr_uid'];
                break;
            default:
                $uid = $item['uid'];
                break;
        }

        if ($type === 'item') {
            $recips = get_iconfig($item['id'], 'activitypub', 'recips');
            if ($recips) {
                $o = '<div class="dropdown-item">' . t('Visible to:') . '</div>';
                $l = [];
                if (isset($recips['to'])) {
                    if (!is_array($recips['to'])) {
                        $recips['to'] = [$recips['to']];
                    }
                    $l = array_merge($l, $recips['to']);
                }
                if (isset($recips['cc'])) {
                    if (!is_array($recips['cc'])) {
                        $recips['cc'] = [$recips['cc']];
                    }
                    $l = array_merge($l, $recips['cc']);
                }
                for ($x = 0; $x < count($l); $x++) {
                    if ($l[$x] === ACTIVITY_PUBLIC_INBOX) {
						$l[$x] = '<strong><em>' . t('Everybody') . '</em></strong>';
					} else {
                        $l[$x] = '<a href="' . $l[$x] . '">' . $l[$x] . '</a>';
                    }
                }
                echo $o . implode('<br>', $l);
                killme();
            }
        }


        if (
            intval($item['item_private']) && (!strlen($item['allow_cid'])) && (!strlen($item['allow_gid']))
            && (!strlen($item['deny_cid'])) && (!strlen($item['deny_gid']))
        ) {
            if ($item['mid'] === $item['parent_mid']) {
                echo '<div class="dropdown-item">' . translate_scope('specific') . '</div>';
                killme();
            }
        }

        $allowed_users = expand_acl($item['allow_cid']);
        $allowed_groups = expand_acl($item['allow_gid']);
        $deny_users = expand_acl($item['deny_cid']);
        $deny_groups = expand_acl($item['deny_gid']);

        $o = '<div class="dropdown-item">' . t('Visible to:') . '</div>';
        $l = [];

        stringify_array_elms($allowed_groups, true);
        stringify_array_elms($allowed_users, true);
        stringify_array_elms($deny_groups, true);
        stringify_array_elms($deny_users, true);


        if (count($allowed_groups)) {
            $r = q("SELECT gname FROM pgrp WHERE hash IN ( " . implode(', ', $allowed_groups) . " )");
            if ($r) {
                foreach ($r as $rr) {
                    $l[] = '<div class="dropdown-item"><b>' . $rr['gname'] . '</b></div>';
                }
            }
        }
        if (count($allowed_users)) {
            $r = q("SELECT xchan_name FROM xchan WHERE xchan_hash IN ( " . implode(', ', $allowed_users) . " )");
            if ($r) {
                foreach ($r as $rr) {
                    $l[] = '<div class="dropdown-item">' . $rr['xchan_name'] . '</div>';
                }
            }
            if ($atokens) {
                foreach ($atokens as $at) {
                    if (in_array("'" . $at['xchan_hash'] . "'", $allowed_users)) {
                        $l[] = '<div class="dropdown-item">' . $at['xchan_name'] . '</div>';
                    }
                }
            }
        }

        if (count($deny_groups)) {
            $r = q("SELECT gname FROM pgrp WHERE hash IN ( " . implode(', ', $deny_groups) . " )");
            if ($r) {
                foreach ($r as $rr) {
                    $l[] = '<div class="dropdown-item"><b><strike>' . $rr['gname'] . '</strike></b></div>';
                }
            }
        }
        if (count($deny_users)) {
            $r = q("SELECT xchan_name FROM xchan WHERE xchan_hash IN ( " . implode(', ', $deny_users) . " )");
            if ($r) {
                foreach ($r as $rr) {
                    $l[] = '<div class="dropdown-item"><strike>' . $rr['xchan_name'] . '</strike></div>';
                }
            }

            if ($atokens) {
                foreach ($atokens as $at) {
                    if (in_array("'" . $at['xchan_hash'] . "'", $deny_users)) {
                        $l[] = '<div class="dropdown-item"><strike>' . $at['xchan_name'] . '</strike></div>';
                    }
                }
            }
        }

        echo $o . implode($l);
        killme();
    }
}
