<?php

namespace Code\Lib;

use Code\Render\Theme;


class AccessList
{

    public static function add($uid, $name, $public = 0)
    {
        $ret = false;
        $hash = new_uuid();

        if ($uid && $name) {
            $r = self::byname($uid, $name); // check for dups
            if ($r !== false) {
                // This could be a problem.
                // Let's assume we've just created a list which we once deleted.
                // All the old members are gone, but the list remains - so we don't break any security
                // access lists. What we're doing here is reviving the dead list, but old content which
                // was restricted to this list may now be seen by the new list members.

                $z = q(
                    "SELECT * FROM pgrp WHERE id = %d LIMIT 1",
                    intval($r)
                );
                if (($z) && $z[0]['deleted']) {
                    q('UPDATE pgrp SET deleted = 0 WHERE id = %d', intval($z[0]['id']));
                    notice(t('A deleted list with this name was revived. Existing item permissions <strong>may</strong> apply to this list and any future members. If this is not what you intended, please create another list with a different name.') . EOL);
                }
                return self::by_id($uid, $r);
            }

            $r = q(
                "INSERT INTO pgrp ( hash, uid, visible, gname, rule )
                VALUES( '%s', %d, %d, '%s', '' ) ",
                dbesc($hash),
                intval($uid),
                intval($public),
                dbesc($name)
            );
            $ret = $r;
        }
        Libsync::build_sync_packet($uid, null, true);
        return (($ret) ? $hash : $ret);
    }


    public static function remove($uid, $name): bool
    {
        $ret = false;
        if ($uid && $name) {
            $r = q(
                "SELECT id, hash FROM pgrp WHERE uid = %d AND gname = '%s' LIMIT 1",
                intval($uid),
                dbesc($name)
            );
            if ($r) {
                $group_id = $r[0]['id'];
                $group_hash = $r[0]['hash'];
            } else {
                return false;
            }

            // remove group from default posting lists
            $r = q(
                "SELECT channel_default_group, channel_allow_gid, channel_deny_gid FROM channel WHERE channel_id = %d LIMIT 1",
                intval($uid)
            );
            if ($r) {
                $user_info = array_shift($r);
                $change = false;

                if ($user_info['channel_default_group'] === $group_hash) {
                    $user_info['channel_default_group'] = '';
                    $change = true;
                }
                if (str_contains($user_info['channel_allow_gid'], '<' . $group_hash . '>')) {
                    $user_info['channel_allow_gid'] = str_replace('<' . $group_hash . '>', '', $user_info['channel_allow_gid']);
                    $change = true;
                }
                if (str_contains($user_info['channel_deny_gid'], '<' . $group_hash . '>')) {
                    $user_info['channel_deny_gid'] = str_replace('<' . $group_hash . '>', '', $user_info['channel_deny_gid']);
                    $change = true;
                }

                if ($change) {
                    q(
                        "UPDATE channel SET channel_default_group = '%s', channel_allow_gid = '%s', channel_deny_gid = '%s'
                        WHERE channel_id = %d",
                        dbesc($user_info['channel_default_group']),
                        dbesc($user_info['channel_allow_gid']),
                        dbesc($user_info['channel_deny_gid']),
                        intval($uid)
                    );
                }
            }

            // remove all members
            $r = q(
                "DELETE FROM pgrp_member WHERE uid = %d AND gid = %d ",
                intval($uid),
                intval($group_id)
            );

            // remove group
            $r = q(
                "UPDATE pgrp SET deleted = 1 WHERE uid = %d AND gname = '%s'",
                intval($uid),
                dbesc($name)
            );

            $ret = (bool)$r;
        }

        Libsync::build_sync_packet($uid, null, true);

        return $ret;
    }

    // returns the integer id of an access group owned by $uid and named $name
    // or false.

    public static function byname($uid, $name): mixed
    {
        if (!($uid && $name)) {
            return false;
        }
        $r = q(
            "SELECT id FROM pgrp WHERE uid = %d AND gname = '%s' LIMIT 1",
            intval($uid),
            dbesc($name)
        );
        if ($r) {
            return $r[0]['id'];
        }
        return false;
    }

    public static function by_id($uid, $id): mixed
    {
        if (!($uid && $id)) {
            return false;
        }

        $r = q(
            "SELECT * FROM pgrp WHERE uid = %d AND id = %d and deleted = 0",
            intval($uid),
            intval($id)
        );
        if ($r) {
            return array_shift($r);
        }
        return false;
    }


    public static function rec_byhash($uid, $hash): mixed
    {
        if (!($uid && $hash)) {
            return false;
        }
        $r = q(
            "SELECT * FROM pgrp WHERE uid = %d AND hash = '%s' LIMIT 1",
            intval($uid),
            dbesc($hash)
        );
        if ($r) {
            return array_shift($r);
        }
        return false;
    }


    public static function member_remove($uid, $name, $member): bool
    {
        $gid = self::byname($uid, $name);
        if (!$gid) {
            return false;
        }
        if (!($uid && $member)) {
            return false;
        }
        $r = q(
            "DELETE FROM pgrp_member WHERE uid = %d AND gid = %d AND xchan = '%s' ",
            intval($uid),
            intval($gid),
            dbesc($member)
        );

        Libsync::build_sync_packet($uid, null, true);

        return (bool)$r;
    }


    public static function member_add($uid, $name, $member, $gid = 0): bool
    {
        if (!$gid) {
            $gid = self::byname($uid, $name);
        }
        if (!($gid && $uid && $member)) {
            return false;
        }

        $r = q(
            "SELECT * FROM pgrp_member WHERE uid = %d AND gid = %d AND xchan = '%s' LIMIT 1",
            intval($uid),
            intval($gid),
            dbesc($member)
        );
        if ($r) {
            return true;    // You might question this, but
            // we indicate success because the group member was in fact created
            // -- It was just created at another time
        } else {
            $r = q(
                "INSERT INTO pgrp_member (uid, gid, xchan)
                VALUES( %d, %d, '%s' ) ",
                intval($uid),
                intval($gid),
                dbesc($member)
            );
        }
        Libsync::build_sync_packet($uid, null, true);
        return (bool)$r;
    }


    public static function members($uid, $gid, $total = false, $start = 0, $records = 0, $sqlExtra = ''): mixed
    {
        $ret = [];
        $pager_sql = '';
        $sql_extra = $sqlExtra;

        if ($records) {
            $pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval($records), intval($start));
        }

        // process virtual groups
        if (str_starts_with($gid, ':')) {
            $vg = substr($gid, 1);
            switch ($vg) {
                case '2':
                    $sql_extra = " and xchan_network in ('nomad','zot6') ";
                    break;
                case '3':
                    $sql_extra = " and xchan_network = 'activitypub' ";
                    break;
                case '1':
                default:
                    break;
            }
            if ($total) {
                $r = q(
                    "SELECT count(*) FROM abook left join xchan on xchan_hash = abook_xchan WHERE abook_channel = %d and xchan_deleted = 0 and abook_self = 0 and abook_blocked = 0 and abook_pending = 0 $sql_extra ORDER BY xchan_name ASC $pager_sql",
                    intval($uid)
                );
                return ($r) ? $r[0]['total'] : false;
            }

            $r = q(
                "SELECT * FROM abook left join xchan on xchan_hash = abook_xchan
                WHERE abook_channel = %d and xchan_deleted = 0 and abook_self = 0 and abook_blocked = 0 
                  and abook_pending = 0 $sql_extra ORDER BY xchan_name ASC $pager_sql",
                intval($uid)
            );
            if ($r) {
                for ($x = 0; $x < count($r); $x++) {
                    $r[$x]['xchan'] = $r[$x]['abook_xchan'];
                }
            }
            return $r;
        }

        if (intval($gid)) {
            if ($total) {
                $r = q(
                    "SELECT count(xchan) as total FROM pgrp_member
                    LEFT JOIN abook ON abook_xchan = pgrp_member.xchan left join xchan on xchan_hash = abook_xchan
                    WHERE gid = %d AND abook_channel = %d and pgrp_member.uid = %d and xchan_deleted = 0 and abook_self = 0
                    and abook_blocked = 0 and abook_pending = 0 $sqlExtra",
                    intval($gid),
                    intval($uid),
                    intval($uid)
                );
                if ($r) {
                    return $r[0]['total'];
                }
            }

            $r = q(
                "SELECT * FROM pgrp_member
                LEFT JOIN abook ON abook_xchan = pgrp_member.xchan left join xchan on xchan_hash = abook_xchan
                WHERE gid = %d AND abook_channel = %d and pgrp_member.uid = %d and xchan_deleted = 0 and abook_self = 0 and abook_blocked = 0 and abook_pending = 0 $sqlExtra ORDER BY xchan_name ASC $pager_sql",
                intval($gid),
                intval($uid),
                intval($uid)
            );
            if ($r) {
                $ret = $r;
            }
        }
        return $ret;
    }

    public static function members_xchan($uid, $gid): array
    {
        $ret = [];
        if (intval($gid)) {
            $r = q(
                "SELECT xchan FROM pgrp_member WHERE gid = %d AND uid = %d",
                intval($gid),
                intval($uid)
            );
            if ($r) {
                foreach ($r as $rv) {
                    $ret[] = $rv['xchan'];
                }
            }
        }
        return $ret;
    }

    public static function select($uid, $group = ''): string
    {

        $grps = [];

        $r = q(
            "SELECT * FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
            intval($uid)
        );
        $grps[] = ['name' => '', 'hash' => '0', 'selected' => ''];
        if ($r) {
            foreach ($r as $rr) {
                $grps[] = ['name' => $rr['gname'], 'id' => $rr['hash'], 'selected' => (($group == $rr['hash']) ? 'true' : '')];
            }
        }

        return replace_macros(Theme::get_template('group_selection.tpl'), [
            '$label' => t('Add new connections to this access list'),
            '$groups' => $grps
        ]);
    }


    public static function widget($every = "connections", $each = "lists", $edit = false, $group_id = 0, $cid = '', $mode = 1): string
    {
        $groups = [];

        $r = q(
            "SELECT * FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
            intval($_SESSION['uid'])
        );
        $member_of = [];
        if ($cid) {
            $member_of = self::containing(local_channel(), $cid);
        }

        if ($r) {
            foreach ($r as $rr) {
                $selected = (($group_id == $rr['id']) ? ' group-selected' : '');

                if ($edit) {
                    $groupedit = ['href' => "lists/" . $rr['id'], 'title' => t('edit')];
                } else {
                    $groupedit = null;
                }

                $groups[] = [
                    'id' => $rr['id'],
                    'enc_cid' => base64url_encode($cid),
                    'cid' => $cid,
                    'text' => $rr['gname'],
                    'selected' => $selected,
                    'href' => (($mode == 0) ? $each . '?f=&gid=' . $rr['id'] : $each . "/" . $rr['id']) . ((x($_GET, 'new')) ? '&new=' . $_GET['new'] : '') . ((x($_GET, 'order')) ? '&order=' . $_GET['order'] : ''),
                    'edit' => $groupedit,
                    'ismember' => in_array($rr['id'], $member_of),
                ];
            }
        }

        return replace_macros(Theme::get_template('group_side.tpl'), [
            '$title' => t('Lists'),
            '$edittext' => t('Edit list'),
            '$createtext' => t('Create new list'),
            '$ungrouped' => (($every === 'contacts') ? t('Channels not in any access list') : ''),
            '$groups' => $groups,
            '$add' => t('add'),
        ]);
    }


    public static function expand($g): array
    {
        if (!(is_array($g) && count($g))) {
            return [];
        }

        $ret = [];
        $x = [];

        foreach ($g as $gv) {
            // virtual access lists
            // connections:abc is all the connection sof the channel with channel_hash abc
            // zot:abc is all of abc's zot6 connections
            // activitypub:abc is all of abc's activitypub connections

            if (str_starts_with($gv, 'connections:') || str_starts_with($gv, 'zot:') || str_starts_with($gv, 'activitypub:')) {
                $sql_extra = EMPTY_STR;
                $channel_hash = substr($gv, strpos($gv, ':') + 1);
                if (str_starts_with($gv, 'zot:')) {
                    $sql_extra = " and xchan_network in ('nomad','zot6') ";
                }
                if (str_starts_with($gv, 'activitypub:')) {
                    $sql_extra = " and xchan_network = 'activitypub' ";
                }
                $r = q(
                    "select channel_id from channel where channel_hash = '%s' ",
                    dbesc($channel_hash)
                );
                if ($r) {
                    foreach ($r as $rv) {
                        $y = q(
                            "select abook_xchan from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d and abook_self = 0 and abook_pending = 0 and abook_archived = 0 $sql_extra",
                            intval($rv['channel_id'])
                        );
                        if ($y) {
                            foreach ($y as $yv) {
                                $ret[] = $yv['abook_xchan'];
                            }
                        }
                    }
                }
            }
            else {
                $x[] = $gv;
            }
        }

        if ($x) {
            stringify_array_elms($x, true);
            $groups = implode(',', $x);
            if ($groups) {
                $r = q("SELECT xchan FROM pgrp_member WHERE gid IN ( select id from pgrp where hash in ( $groups ))");
                if ($r) {
                    foreach ($r as $rv) {
                        $ret[] = $rv['xchan'];
                    }
                }
            }
        }
        return $ret;
    }


    /** @noinspection PhpUnused */
    public static function member_of($c): array|bool
    {
        return q(
            "SELECT pgrp.gname, pgrp.id FROM pgrp LEFT JOIN pgrp_member ON pgrp_member.gid = pgrp.id
            WHERE pgrp_member.xchan = '%s' AND pgrp.deleted = 0 ORDER BY pgrp.gname  ASC ",
            dbesc($c)
        );
    }

    public static function containing($uid, $c): array
    {

        $r = q(
            "SELECT gid FROM pgrp_member WHERE uid = %d AND pgrp_member.xchan = '%s' ",
            intval($uid),
            dbesc($c)
        );

        $ret = [];
        if ($r) {
            foreach ($r as $rv) {
                $ret[] = $rv['gid'];
            }
        }
        return $ret;
    }
}
