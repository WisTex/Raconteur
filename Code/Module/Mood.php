<?php

namespace Code\Module;

use App;
use Code\Lib\Apps;
use Code\Web\Controller;
use Code\Daemon\Run;
use Code\Lib\Navbar;
use Code\Extend\Hook;
use Code\Render\Theme;


require_once('include/security.php');
require_once('include/bbcode.php');


class Mood extends Controller
{

    public function init()
    {

        if (!local_channel()) {
            return;
        }

        if (!Apps::system_app_installed(local_channel(), 'Mood')) {
            return;
        }

        $uid = local_channel();
        $channel = App::get_channel();
        $verb = notags(trim($_GET['verb']));

        if (!$verb) {
            return;
        }

        $verbs = get_mood_verbs();

        if (!array_key_exists($verb, $verbs)) {
            return;
        }

        $activity = ACTIVITY_MOOD . '#' . urlencode($verb);

        $parent = ((x($_GET, 'parent')) ? intval($_GET['parent']) : 0);


        logger('mood: verb ' . $verb, LOGGER_DEBUG);


        if ($parent) {
            $r = q(
                "select mid, owner_xchan, private, allow_cid, allow_gid, deny_cid, deny_gid 
				from item where id = %d and parent = %d and uid = %d limit 1",
                intval($parent),
                intval($parent),
                intval($uid)
            );
            if (count($r)) {
                $parent_mid = $r[0]['mid'];
                $private = $r[0]['item_private'];
                $allow_cid = $r[0]['allow_cid'];
                $allow_gid = $r[0]['allow_gid'];
                $deny_cid = $r[0]['deny_cid'];
                $deny_gid = $r[0]['deny_gid'];
            }
        } else {
            $private = 0;

            $allow_cid = $channel['channel_allow_cid'];
            $allow_gid = $channel['channel_allow_gid'];
            $deny_cid = $channel['channel_deny_cid'];
            $deny_gid = $channel['channel_deny_gid'];
        }

        $poster = App::get_observer();

        $uuid = new_uuid();
        $mid = z_root() . '/item/' . $uuid;

        $action = sprintf(t('%1$s is %2$s', 'mood'), '[zrl=' . $poster['xchan_url'] . ']' . $poster['xchan_name'] . '[/zrl]', $verbs[$verb]);

        $arr = [];

        $arr['aid'] = get_account_id();
        $arr['uid'] = $uid;
        $arr['uuid'] = $uuid;
        $arr['mid'] = $mid;
        $arr['parent_mid'] = (($parent_mid) ? $parent_mid : $mid);
        $arr['author_xchan'] = $poster['xchan_hash'];
        $arr['owner_xchan'] = (($parent_mid) ? $r[0]['owner_xchan'] : $poster['xchan_hash']);
        $arr['title'] = '';
        $arr['allow_cid'] = $allow_cid;
        $arr['allow_gid'] = $allow_gid;
        $arr['deny_cid'] = $deny_cid;
        $arr['deny_gid'] = $deny_gid;
        $arr['item_private'] = $private;
        $arr['verb'] = $activity;
        $arr['body'] = $action;
        $arr['item_origin'] = 1;
        $arr['item_wall'] = 1;
        $arr['item_unseen'] = 1;
        if (!$parent_mid) {
            $item['item_thread_top'] = 1;
        }

        if ((!$arr['plink']) && intval($arr['item_thread_top'])) {
            $arr['plink'] = z_root() . '/channel/' . $channel['channel_address'] . '/?f=&mid=' . urlencode($arr['mid']);
        }


        $post = item_store($arr);
        $item_id = $post['item_id'];

        if ($item_id) {
            Run::Summon(['Notifier', 'activity', $item_id]);
        }

        Hook::call('post_local_end', $arr);

        if ($_SESSION['return_url']) {
            goaway(z_root() . '/' . $_SESSION['return_url']);
        }

        return;
    }


    public function get()
    {

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return '';
        }

        if (!Apps::system_app_installed(local_channel(), 'Mood')) {
            //Do not display any associated widgets at this point
            App::$pdl = '';

            $o = '<b>Mood App (Not Installed):</b><br>';
            $o .= t('Set your current mood and tell your friends');
            return $o;
        }

        Navbar::set_selected('Mood');

        $parent = ((x($_GET, 'parent')) ? intval($_GET['parent']) : '0');

        $verbs = get_mood_verbs();

        $shortlist = [];
        foreach ($verbs as $k => $v) {
            if ($v !== 'NOTRANSLATION') {
                $shortlist[] = array($k, $v);
            }
        }


        $tpl = Theme::get_template('mood_content.tpl');

        $o = replace_macros($tpl, array(
            '$title' => t('Mood'),
            '$desc' => t('Set your current mood and tell your friends'),
            '$verbs' => $shortlist,
            '$parent' => $parent,
            '$submit' => t('Submit'),
        ));

        return $o;
    }
}
