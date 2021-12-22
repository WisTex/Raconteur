<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;

require_once("include/bbcode.php");
require_once('include/security.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');


class Profile extends Controller
{

    public function init()
    {

        if (argc() > 1) {
            $which = argv(1);
        } else {
            notice(t('Requested profile is not available.') . EOL);
            App::$error = 404;
            return;
        }

        nav_set_selected('Profile');

        $profile = '';
        $channel = App::get_channel();

        if ((local_channel()) && (argc() > 2) && (argv(2) === 'view')) {
            $which = $channel['channel_address'];
            $profile = argv(1);
            $r = q(
                "select profile_guid from profile where id = %d and uid = %d limit 1",
                intval($profile),
                intval(local_channel())
            );
            if (!$r) {
                $profile = '';
            }
            $profile = $r[0]['profile_guid'];
        }

        head_add_link([
            'rel' => 'alternate',
            'type' => 'application/atom+xml',
            'title' => t('Posts and comments'),
            'href' => z_root() . '/feed/' . $which
        ]);

        head_add_link([
            'rel' => 'alternate',
            'type' => 'application/atom+xml',
            'title' => t('Only posts'),
            'href' => z_root() . '/feed/' . $which . '?f=&top=1'
        ]);


        if (!$profile) {
            $x = q(
                "select channel_id as profile_uid from channel where channel_address = '%s' limit 1",
                dbesc(argv(1))
            );
            if ($x) {
                App::$profile = $x[0];
            }
        }


        if (ActivityStreams::is_as_request()) {
            $chan = channelx_by_nick(argv(1));
            if (!$chan) {
                http_status_exit(404, 'Not found');
            }
            $p = Activity::encode_person($chan, true, ((get_config('system', 'activitypub', ACTIVITYPUB_ENABLED)) ? true : false));
            if (!$p) {
                http_status_exit(404, 'Not found');
            }

            as_return_and_die(['type' => 'Profile', 'describes' => $p], $chan);
        }

        Libprofile::load($which, $profile);
    }

    public function get()
    {

        if (observer_prohibited(true)) {
            return login();
        }

        $groups = [];


        $tab = 'profile';
        $o = '';

        if (!(perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_profile'))) {
            notice(t('Permission denied.') . EOL);
            return;
        }


        if (argc() > 2 && argv(2) === 'vcard') {
            header('Content-type: text/vcard');
            header('Content-Disposition: attachment; filename="' . t('vcard') . '-' . $profile['channel_address'] . '.vcf"');
            echo App::$profile['profile_vcard'];
            killme();
        }

        $is_owner = ((local_channel()) && (local_channel() == App::$profile['profile_uid']) ? true : false);

        if (App::$profile['hidewall'] && (!$is_owner) && (!remote_channel())) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        head_add_link([
            'rel' => 'alternate',
            'type' => 'application/json+oembed',
            'href' => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
            'title' => 'oembed'
        ]);

        $o .= Libprofile::advanced();
        call_hooks('profile_advanced', $o);
        return $o;
    }
}
