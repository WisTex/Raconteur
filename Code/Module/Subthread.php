<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Activity;
use Code\Lib\Channel;
use Code\Extend\Hook;

require_once('include/security.php');
require_once('include/bbcode.php');


class Subthread extends Controller
{

    public function get()
    {

        if (!local_channel()) {
            return;
        }

        $sys = Channel::get_system();
        $channel = App::get_channel();

        $item_id = ((argc() > 2) ? notags(trim(argv(2))) : 0);

        if (argv(1) === 'sub') {
            $activity = ACTIVITY_FOLLOW;
        } elseif (argv(1) === 'unsub') {
            $activity = ACTIVITY_IGNORE;
        }

        $i = q(
            "select * from item where id = %d and uid = %d",
            intval($item_id),
            intval(local_channel())
        );

        if (!$i) {
            // try the global public stream
            $i = q(
                "select * from item where id = %d and uid = %d",
                intval($postid),
                intval($sys['channel_id'])
            );
            // try the local public stream
            if (!$i) {
                $i = q(
                    "select * from item where id = %d and item_wall = 1 and item_private = 0",
                    intval($postid)
                );
            }

            if ($i && local_channel() && (!Channel::is_system(local_channel()))) {
                $i = [copy_of_pubitem($channel, $i[0]['mid'])];
                $item_id = (($i) ? $i[0]['id'] : 0);
            }
        }

        if (!$i) {
            return;
        }

        $r = q(
            "select * from item where id = parent and id = %d limit 1",
            dbesc($i[0]['parent'])
        );

        if ((!$item_id) || (!$r)) {
            logger('subthread: no item ' . $item_id);
            return;
        }

        $item = $r[0];

        $owner_uid = $item['uid'];
        $observer = App::get_observer();
        $ob_hash = (($observer) ? $observer['xchan_hash'] : '');

        if (!perm_is_allowed($owner_uid, $ob_hash, 'post_comments')) {
            return;
        }

        $sys = Channel::get_system();

        $owner_uid = $item['uid'];
        $owner_aid = $item['aid'];

        // if this is a "discover" item, (item['uid'] is the sys channel),
        // fallback to the item comment policy, which should've been
        // respected when generating the conversation thread.
        // Even if the activity is rejected by the item owner, it should still get attached
        // to the local discover conversation on this site.

        if (($owner_uid != $sys['channel_id']) && (!perm_is_allowed($owner_uid, $observer['xchan_hash'], 'post_comments'))) {
            notice(t('Permission denied') . EOL);
            killme();
        }

        $r = q(
            "select * from xchan where xchan_hash = '%s' limit 1",
            dbesc($item['owner_xchan'])
        );
        if ($r) {
            $thread_owner = $r[0];
        } else {
            killme();
        }

        $r = q(
            "select * from xchan where xchan_hash = '%s' limit 1",
            dbesc($item['author_xchan'])
        );
        if ($r) {
            $item_author = $r[0];
        } else {
            killme();
        }

        $uuid = new_uuid();
        $mid = z_root() . '/item/' . $uuid;

        $post_type = (($item['resource_type'] === 'photo') ? t('photo') : t('status'));

        $links = array(array('rel' => 'alternate', 'type' => 'text/html', 'href' => $item['plink']));
        $objtype = (($item['resource_type'] === 'photo') ? ACTIVITY_OBJ_PHOTO : ACTIVITY_OBJ_NOTE);

        $body = $item['body'];

        $obj = Activity::fetch_item(['id' => $item['mid']]);
        $objtype = $obj['type'];

        if (!intval($item['item_thread_top'])) {
            $post_type = 'comment';
        }

        if ($activity === ACTIVITY_FOLLOW) {
            $bodyverb = t('%1$s is following %2$s\'s %3$s');
        }
        if ($activity === ACTIVITY_IGNORE) {
            $bodyverb = t('%1$s stopped following %2$s\'s %3$s');
        }

        $arr = [];

        $arr['uuid'] = $uuid;
        $arr['mid'] = $mid;
        $arr['aid'] = $owner_aid;
        $arr['uid'] = $owner_uid;
        $arr['parent'] = $item['id'];
        $arr['parent_mid'] = $item['mid'];
        $arr['thr_parent'] = $item['mid'];
        $arr['owner_xchan'] = $thread_owner['xchan_hash'];
        $arr['author_xchan'] = $observer['xchan_hash'];
        $arr['item_origin'] = 1;
        $arr['item_notshown'] = 1;

        if (intval($item['item_wall'])) {
            $arr['item_wall'] = 1;
        } else {
            $arr['item_wall'] = 0;
        }

        $ulink = '[zrl=' . $item_author['xchan_url'] . ']' . $item_author['xchan_name'] . '[/zrl]';
        $alink = '[zrl=' . $observer['xchan_url'] . ']' . $observer['xchan_name'] . '[/zrl]';
        $plink = '[zrl=' . z_root() . '/display/?mid=' . gen_link_id($item['mid']) . ']' . $post_type . '[/zrl]';

        $arr['body'] = sprintf($bodyverb, $alink, $ulink, $plink);

        $arr['verb'] = $activity;
        $arr['obj_type'] = $objtype;
        $arr['obj'] = json_encode($obj);

        $arr['allow_cid'] = $item['allow_cid'];
        $arr['allow_gid'] = $item['allow_gid'];
        $arr['deny_cid'] = $item['deny_cid'];
        $arr['deny_gid'] = $item['deny_gid'];

        $post = item_store($arr);
        $post_id = $post['item_id'];

        $arr['id'] = $post_id;

        Hook::call('post_local_end', $arr);

        killme();
    }
}
