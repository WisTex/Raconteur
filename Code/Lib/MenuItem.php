<?php

namespace Code\Lib;

use Code\Lib\Libsync;
use Code\Lib\Channel;
use Code\Access\AccessControl;
    
require_once('include/security.php');
require_once('include/bbcode.php');


class MenuItem
{
    public static function add($menu_id, $uid, $arr)
    {

        $mitem_link = escape_tags($arr['mitem_link']);
        $mitem_desc = escape_tags($arr['mitem_desc']);
        $mitem_order = intval($arr['mitem_order']);
        $mitem_flags = intval($arr['mitem_flags']);

        if (local_channel() == $uid) {
            $channel = App::get_channel();
        }

        $acl = new AccessControl($channel);
        $acl->set_from_array($arr);
        $p = $acl->get();

        $r = q(
            "insert into menu_item ( mitem_link, mitem_desc, mitem_flags, allow_cid, allow_gid, deny_cid, deny_gid, mitem_channel_id, mitem_menu_id, mitem_order ) values ( '%s', '%s', %d, '%s', '%s', '%s', '%s', %d, %d, %d ) ",
            dbesc($mitem_link),
            dbesc($mitem_desc),
            intval($mitem_flags),
            dbesc($p['allow_cid']),
            dbesc($p['allow_gid']),
            dbesc($p['deny_cid']),
            dbesc($p['deny_gid']),
            intval($uid),
            intval($menu_id),
            intval($mitem_order)
        );

        $x = q(
            "update menu set menu_edited = '%s' where menu_id = %d and menu_channel_id = %d",
            dbesc(datetime_convert()),
            intval($menu_id),
            intval($uid)
        );

        return $r;
    }

    public static function edit($menu_id, $uid, $arr)
    {


        $mitem_id = intval($arr['mitem_id']);
        $mitem_link = escape_tags($arr['mitem_link']);
        $mitem_desc = escape_tags($arr['mitem_desc']);
        $mitem_order = intval($arr['mitem_order']);
        $mitem_flags = intval($arr['mitem_flags']);


        if (local_channel() == $uid) {
            $channel = App::get_channel();
        }

        $acl = new AccessControl($channel);
        $acl->set_from_array($arr);
        $p = $acl->get();


        $r = q(
            "update menu_item set mitem_link = '%s', mitem_desc = '%s', mitem_flags = %d, allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s', mitem_order = %d  where mitem_channel_id = %d and mitem_menu_id = %d and mitem_id = %d",
            dbesc($mitem_link),
            dbesc($mitem_desc),
            intval($mitem_flags),
            dbesc($p['allow_cid']),
            dbesc($p['allow_gid']),
            dbesc($p['deny_cid']),
            dbesc($p['deny_gid']),
            intval($mitem_order),
            intval($uid),
            intval($menu_id),
            intval($mitem_id)
        );

        $x = q(
            "update menu set menu_edited = '%s' where menu_id = %d and menu_channel_id = %d",
            dbesc(datetime_convert()),
            intval($menu_id),
            intval($uid)
        );

        return $r;
    }




    public static function delete($menu_id, $uid, $item_id)
    {
        $r = q(
            "delete from menu_item where mitem_menu_id = %d and mitem_channel_id = %d and mitem_id = %d",
            intval($menu_id),
            intval($uid),
            intval($item_id)
        );

        $x = q(
            "update menu set menu_edited = '%s' where menu_id = %d and menu_channel_id = %d",
            dbesc(datetime_convert()),
            intval($menu_id),
            intval($uid)
        );

        return $r;
    }

}
