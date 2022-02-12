<?php

namespace Zotlabs\Lib;

use App;
use Zotlabs\Daemon\Run;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Channel;
use Zotlabs\Extend\Hook;

class Share
{

    private $item = null;
    private $attach = null;

    public function __construct($post_id)
    {

        if (! $post_id) {
            return;
        }

        if (is_array($post_id)) {
            $this->item = $post_id;
            return;
        }

        if (! (local_channel() || remote_channel())) {
            return;
        }

        $r = q(
            "SELECT * from item left join xchan on author_xchan = xchan_hash WHERE id = %d  LIMIT 1",
            intval($post_id)
        );
        if (! $r) {
            return;
        }

        if (($r[0]['item_private']) && ($r[0]['xchan_network'] !== 'rss')) {
            return;
        }

        $sql_extra = item_permissions_sql($r[0]['uid']);

        $r = q(
            "select * from item where id = %d $sql_extra",
            intval($post_id)
        );
        if (! $r) {
            return;
        }

        if (! in_array($r[0]['mimetype'], [ 'text/bbcode', 'text/x-multicode' ])) {
            return;
        }

        /** @FIXME eventually we want to post remotely via rpost on your home site */
        // When that works remove this next bit:

        if (! local_channel()) {
            return;
        }

        xchan_query($r);

        $this->item = array_shift($r);

        $arr = [];

        $owner_uid = $this->item['uid'];
        $owner_aid = $this->item['aid'];

        $channel = Channel::from_id($this->item['uid']);
        $observer = App::get_observer();

        $can_comment = false;
        if ((array_key_exists('owner', $this->item)) && intval($this->item['owner']['abook_self'])) {
            $can_comment = perm_is_allowed($this->item['uid'], $observer['xchan_hash'], 'post_comments');
        } else {
            $can_comment = can_comment_on_post($observer['xchan_hash'], $this->item);
        }

        if (! $can_comment) {
            return;
        }

        $r = q(
            "select * from xchan where xchan_hash = '%s' limit 1",
            dbesc($this->item['owner_xchan'])
        );

        if ($r) {
            $thread_owner = array_shift($r);
        } else {
            return;
        }

        $r = q(
            "select * from xchan where xchan_hash = '%s' limit 1",
            dbesc($this->item['author_xchan'])
        );
        if ($r) {
            $item_author = array_shift($r);
        } else {
            return;
        }

        if (! $this->attach) {
            $this->attach = [];
        }
    
        $this->attach[] = [
            'href' => $this->item['message_id'],
            'type' => 'application/activity+json',
            'title' => $this->item['message_id']            
        ];
    
        if ($item_author['network'] === 'activitypub') {
            // for Mastodon compatibility, send back an ActivityPub Announce activity.
            // We don't need or want these on our own network as there is no mechanism for providing
            // a fair-use defense to copyright claims and frivolous lawsuits.

            $arr['aid'] = $owner_aid;
            $arr['uid'] = $owner_uid;

            $arr['item_origin'] = 1;
            $arr['item_wall'] = $this->item['item_wall'];
            $arr['uuid'] = new_uuid();
            $arr['mid'] = z_root() . '/item/' . $arr['uuid'];
            $arr['mid'] = str_replace('/item/', '/activity/', $arr['mid']);
            $arr['parent_mid'] = $this->item['mid'];

            $mention = '@[zrl=' . $this->item['author']['xchan_url'] . ']' . $this->item['author']['xchan_name'] . '[/zrl]';
            $arr['body'] = sprintf(t('&#x1f501; Repeated %1$s\'s %2$s'), $mention, $this->item['obj_type']);

            $arr['author_xchan'] = $observer['xchan_hash'];
            $arr['owner_xchan']  = $this->item['author_xchan'];
            $arr['obj'] = $this->item['obj'];
            $arr['obj_type'] = $this->item['obj_type'];
            $arr['verb'] = 'Announce';

            $post = item_store($arr);

            $post_id = $post['item_id'];

            $arr['id'] = $post_id;

            Hook::call('post_local_end', $arr);

            $r = q(
                "select * from item where id = %d",
                intval($post_id)
            );
            if ($r) {
                xchan_query($r);
                $sync_item = fetch_post_tags($r);
                Libsync::build_sync_packet($channel['channel_id'], [ 'item' => [ encode_item($sync_item[0], true) ] ]);
            }

            Run::Summon([ 'Notifier','like',$post_id ]);
        }

        return;
    }

    public function obj()
    {
        $obj = [];

        if (! $this->item) {
            return $obj;
        }

        $obj['type']         = $this->item['obj_type'];
        $obj['id']           = $this->item['mid'];
        $obj['content']      = bbcode($this->item['body']);
        $obj['source'] = [
            'mediaType' => $this->item['mimetype'],
            'content'   => $this->item['body']
        ];

        $obj['name']          = $this->item['title'];
        $obj['published']     = $this->item['created'];
        $obj['updated']       = $this->item['edited'];
        $obj['attributedTo']  =  ((strpos($this->item['author']['xchan_hash'], 'http') === 0)
            ? $this->item['author']['xchan_hash']
            : $this->item['author']['xchan_url']);

        return $obj;
    }

    public function attach()
    {
        return $this->attach;
    }
    
    public function bbcode()
    {
        $bb = EMPTY_STR;

        if (! $this->item) {
            return $bb;
        }

        if (! $this->item['author']) {
            $author = q(
                "select * from xchan where xchan_hash = '%s' limit 1",
                dbesc($this->item['author_xchan'])
            );
            if ($author) {
                $this->item['author'] = array_shift($author);
            }
        }

        $special_object = (in_array($this->item['obj_type'], [ ACTIVITY_OBJ_PHOTO, 'Event', 'Question' ]) ? true : false);
        if ($special_object) {
            $object = json_decode($this->item['obj'], true);
            $special = (($object['source']) ? $object['source']['content'] : $object['body']);
        }

        if (strpos($this->item['body'], "[/share]") !== false) {
            $pos = strpos($this->item['body'], "[share");
            $bb = substr($this->item['body'], $pos);
        } else {
            $bb = "[share author='" . urlencode($this->item['author']['xchan_name']) .
                "' profile='"       . $this->item['author']['xchan_url'] .
                "' portable_id='"   . $this->item['author']['xchan_hash'] .
                "' avatar='"        . $this->item['author']['xchan_photo_s'] .
                "' link='"          . $this->item['plink'] .
				"' auth='"          . (in_array($this->item['author']['network'],['nomad','zot6']) ? 'true' : 'false') .
                "' posted='"        . $this->item['created'] .
                "' message_id='"    . $this->item['mid'] .
            "']";
            if ($this->item['title']) {
                $bb .= '[b]' . $this->item['title'] . '[/b]' . "\r\n";
            }
            if ($this->item['summary']) {
                $bb .= $this->item['summary'] . "\r\n";
            }

            $bb .= (($special_object) ? $special . "\r\n" . $this->item['body'] : $this->item['body']);
            $bb .= "[/share]";
        }

        return $bb;
    }
}
