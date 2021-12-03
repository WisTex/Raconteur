<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Daemon\Run;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

require_once('include/security.php');
require_once('include/bbcode.php');


class Share extends Controller
{

    public function init()
    {

        $post_id = ((argc() > 1) ? intval(argv(1)) : 0);

        if (!$post_id) {
            killme();
        }

        if (!local_channel()) {
            killme();
        }

        $observer = App::get_observer();

        $channel = App::get_channel();


        $r = q(
            "SELECT * from item left join xchan on author_xchan = xchan_hash WHERE id = %d  LIMIT 1",
            intval($post_id)
        );
        if (!$r) {
            killme();
        }

        if (($r[0]['item_private']) && ($r[0]['xchan_network'] !== 'rss')) {
            killme();
        }

        $sql_extra = item_permissions_sql($r[0]['uid']);

        $r = q(
            "select * from item where id = %d $sql_extra",
            intval($post_id)
        );
        if (!$r) {
            killme();
        }

        /** @FIXME we only share bbcode */

        if (!in_array($r[0]['mimetype'], ['text/bbcode', 'text/x-multicode'])) {
            killme();
        }


        xchan_query($r);

        $arr = [];

        $item = $r[0];

        $owner_uid = $r[0]['uid'];
        $owner_aid = $r[0]['aid'];

        $can_comment = false;
        if ((array_key_exists('owner', $item)) && intval($item['owner']['abook_self'])) {
            $can_comment = perm_is_allowed($item['uid'], $observer['xchan_hash'], 'post_comments');
        } else {
            $can_comment = can_comment_on_post($observer['xchan_hash'], $item);
        }

        if (!$can_comment) {
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


        $arr['aid'] = $owner_aid;
        $arr['uid'] = $owner_uid;

        $arr['item_origin'] = 1;
        $arr['item_wall'] = $item['item_wall'];
        $arr['uuid'] = new_uuid();
        $arr['mid'] = z_root() . '/item/' . $arr['uuid'];
        $arr['mid'] = str_replace('/item/', '/activity/', $arr['mid']);
        $arr['parent_mid'] = $item['mid'];

        $mention = '@[zrl=' . $item['author']['xchan_url'] . ']' . $item['author']['xchan_name'] . '[/zrl]';
        $arr['body'] = sprintf(t('&#x1f501; Repeated %1$s\'s %2$s'), $mention, $item['obj_type']);

        $arr['author_xchan'] = $channel['channel_hash'];
        $arr['owner_xchan'] = $item['author_xchan'];
        $arr['obj'] = $item['obj'];
        $arr['obj_type'] = $item['obj_type'];
        $arr['verb'] = 'Announce';

        $post = item_store($arr);

        $post_id = $post['item_id'];

        $arr['id'] = $post_id;

        call_hooks('post_local_end', $arr);

        info(t('Post repeated') . EOL);

        $r = q(
            "select * from item where id = %d",
            intval($post_id)
        );
        if ($r) {
            xchan_query($r);
            $sync_item = fetch_post_tags($r);
            Libsync::build_sync_packet($channel['channel_id'], ['item' => [encode_item($sync_item[0], true)]]);
        }

        Run::Summon(['Notifier', 'like', $post_id]);

        killme();
    }
}
