<?php

/** @file */

namespace Code\Module;

use App;
use Code\Lib\Apps;
use Code\Web\Controller;
use Code\Lib\Chatroom;
use Code\Lib\Libsync;
use Code\Lib\Libprofile;
use Code\Access\AccessControl;
use Code\Lib\Navbar;
use Code\Lib\Libacl;
use Code\Render\Theme;


class Chat extends Controller
{

    public function init()
    {

        $which = ((argc() > 1) ? argv(1) : null);
        if (local_channel() && (!$which)) {
            $channel = App::get_channel();
            if ($channel && $channel['channel_address']) {
                $which = $channel['channel_address'];
            }
        }

        if (!$which) {
            notice(t('You must be logged in to see this page.') . EOL);
            return;
        }

        $profile = 0;

        // Run Libprofile::load() here to make sure the theme is set before
        // we start loading content

        Libprofile::load($which, $profile);
    }

    public function post()
    {

        if ($_POST['room_name']) {
            $room = strip_tags(trim($_POST['room_name']));
        }

        if ((!$room) || (!local_channel())) {
            return;
        }

        $channel = App::get_channel();

        if ($_POST['action'] === 'drop') {
            logger('delete chatroom');
            Chatroom::destroy($channel, ['cr_name' => $room]);
            goaway(z_root() . '/chat/' . $channel['channel_address']);
        }

        $acl = new AccessControl($channel);
        $acl->set_from_array($_REQUEST);

        $arr = $acl->get();
        $arr['name'] = $room;
        $arr['expire'] = intval($_POST['chat_expire']);
        if (intval($arr['expire']) < 0) {
            $arr['expire'] = 0;
        }

        Chatroom::create($channel, $arr);

        $x = q(
            "select * from chatroom where cr_name = '%s' and cr_uid = %d limit 1",
            dbesc($room),
            intval(local_channel())
        );

        Libsync::build_sync_packet(0, array('chatroom' => $x));

        if ($x) {
            goaway(z_root() . '/chat/' . $channel['channel_address'] . '/' . $x[0]['cr_id']);
        }

        // that failed. Try again perhaps?

        goaway(z_root() . '/chat/' . $channel['channel_address'] . '/new');
    }


    public function get()
    {

//      if(! Apps::system_app_installed(App::$profile_uid, 'Chatrooms')) {
//          // Do not display any associated widgets at this point
//          App::$pdl = '';
//
//          $o = '<b>Chatrooms App (Not Installed):</b><br>';
//          $o .= t('Access Controlled Chatrooms');
//          return $o;
//      }

        if (local_channel()) {
            $channel = App::get_channel();
            Navbar::set_selected('Chatrooms');
        }

        $ob = App::get_observer();
        $observer = get_observer_hash();
        if (!$observer) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if (!perm_is_allowed(App::$profile['profile_uid'], $observer, 'chat')) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if ((argc() > 3) && intval(argv(2)) && (argv(3) === 'leave')) {
            Chatroom::leave($observer, argv(2), $_SERVER['REMOTE_ADDR']);
            goaway(z_root() . '/channel/' . argv(1));
        }


        if ((argc() > 3) && intval(argv(2)) && (argv(3) === 'status')) {
            $ret = ['success' => false];
            $room_id = intval(argv(2));
            if (!$room_id || !$observer) {
                return;
            }

            $r = q(
                "select * from chatroom where cr_id = %d limit 1",
                intval($room_id)
            );
            if (!$r) {
                json_return_and_die($ret);
            }
            require_once('include/security.php');
            $sql_extra = permissions_sql($r[0]['cr_uid']);

            $x = q(
                "select * from chatroom where cr_id = %d and cr_uid = %d $sql_extra limit 1",
                intval($room_id),
                intval($r[0]['cr_uid'])
            );
            if (!$x) {
                json_return_and_die($ret);
            }
            $y = q(
                "select count(*) as total from chatpresence where cp_room = %d",
                intval($room_id)
            );
            if ($y) {
                $ret['success'] = true;
                $ret['chatroom'] = $r[0]['cr_name'];
                $ret['inroom'] = $y[0]['total'];
            }

            // figure out how to present a timestamp of the last activity, since we don't know the observer's timezone.

            $z = q(
                "select created from chat where chat_room = %d order by created desc limit 1",
                intval($room_id)
            );
            if ($z) {
                $ret['last'] = $z[0]['created'];
            }
            json_return_and_die($ret);
        }


        if (argc() > 2 && intval(argv(2))) {
            $room_id = intval(argv(2));

            $x = Chatroom::enter($observer, $room_id, 'online', $_SERVER['REMOTE_ADDR']);
            if (!$x) {
                return;
            }
            $x = q(
                "select * from chatroom where cr_id = %d and cr_uid = %d $sql_extra limit 1",
                intval($room_id),
                intval(App::$profile['profile_uid'])
            );

            if ($x) {
                $acl = new AccessControl([]);
                $acl->set($x[0]);

                $private = $acl->is_private();
                $room_name = $x[0]['cr_name'];
            } else {
                notice(t('Room not found') . EOL);
                return '';
            }

            $cipher = get_pconfig(local_channel(), 'system', 'default_cipher');
            if (!$cipher) {
                $cipher = 'AES-128-CCM';
            }


            $o = replace_macros(Theme::get_template('chat.tpl'), [
                '$is_owner' => ((local_channel() && local_channel() == $x[0]['cr_uid']) ? true : false),
                '$room_name' => $room_name,
                '$room_id' => $room_id,
                '$baseurl' => z_root(),
                '$nickname' => argv(1),
                '$submit' => t('Submit'),
                '$leave' => t('Leave Room'),
                '$drop' => t('Delete Room'),
                '$away' => t('I am away right now'),
                '$online' => t('I am online'),
                '$feature_encrypt' => ((Apps::system_app_installed(local_channel(), 'Secrets')) ? true : false),
                '$cipher' => $cipher,
                '$linkurl' => t('Please enter a link URL:'),
                '$encrypt' => t('Encrypt text'),
                '$insert' => t('Insert web link')
            ]);
            return $o;
        }

        require_once('include/conversation.php');

        $o = '';

        $acl = new AccessControl($channel);
        $channel_acl = $acl->get();

        $lockstate = (($channel_acl['allow_cid'] || $channel_acl['allow_gid'] || $channel_acl['deny_cid'] || $channel_acl['deny_gid']) ? 'lock' : 'unlock');

        $chatroom_new = '';
        if (local_channel()) {
            $chatroom_new = replace_macros(Theme::get_template('chatroom_new.tpl'), [
                '$header' => t('New Chatroom'),
                '$name' => ['room_name', t('Chatroom name'), '', ''],
                '$chat_expire' => ['chat_expire', t('Expiration of chats (minutes)'), 120, ''],
                '$permissions' => t('Permissions'),
                '$acl' => Libacl::populate($channel_acl, false),
                '$allow_cid' => acl2json($channel_acl['allow_cid']),
                '$allow_gid' => acl2json($channel_acl['allow_gid']),
                '$deny_cid' => acl2json($channel_acl['deny_cid']),
                '$deny_gid' => acl2json($channel_acl['deny_gid']),
                '$lockstate' => $lockstate,
                '$submit' => t('Submit')

            ]);
        }

        $rooms = Chatroom::roomlist(App::$profile['profile_uid']);

        $o .= replace_macros(Theme::get_template('chatrooms.tpl'), [
            '$header' => sprintf(t('%1$s\'s Chatrooms'), App::$profile['fullname']),
            '$name' => t('Name'),
            '$baseurl' => z_root(),
            '$nickname' => App::$profile['channel_address'],
            '$rooms' => $rooms,
            '$norooms' => t('No chatrooms available'),
            '$newroom' => t('Create New'),
            '$is_owner' => ((local_channel() && local_channel() == App::$profile['profile_uid']) ? 1 : 0),
            '$chatroom_new' => $chatroom_new,
            '$expire' => t('Expiration'),
            '$expire_unit' => t('min') //minutes
        ]);

        return $o;
    }
}
