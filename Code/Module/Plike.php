<?php

namespace Code\Module;

use App;
use Code\Lib\Libsync;
use Code\Lib\Activity;
use Code\Web\Controller;
use Code\Daemon\Run;
use Code\Lib\Channel;
use Code\Extend\Hook;

require_once('include/security.php');
require_once('include/bbcode.php');
require_once('include/event.php');

class Plike extends Controller
{

    private function reaction_to_activity($reaction)
    {

        $undo = false;

        $acts = [
            'like'        => 'Like',
            'dislike'     => 'Dislike',
        ];

        // unlike (etc.) reactions are an undo of positive reactions, rather than a negative action.
        // The activity is the same in undo actions and will have the same activity mapping

        if (substr($reaction, 0, 2) === 'un') {
            $undo = true;
            $reaction = substr($reaction, 2);
        }

        if (array_key_exists($reaction, $acts)) {
            return (($undo) ? 'Undo/' : EMPTY_STR) . $acts[$reaction];
        }

        return EMPTY_STR;
    }



    public function get()
    {

        $undo = false;
        $object = $target = null;
        $owner_uid = 0;
        $post_type = EMPTY_STR;
        $objtype = EMPTY_STR;
        $allow_cid = $allow_gid = $deny_cid = $deny_gid = '';
        $output = EMPTY_STR;

        $sys_channel = Channel::get_system();
        $sys_channel_id = (($sys_channel) ? $sys_channel['channel_id'] : 0);

        $observer = App::get_observer();

        $verb = ((array_key_exists('verb', $_GET)) ? notags(trim($_GET['verb'])) :  EMPTY_STR);

        // Figure out what action we're performing

        $activity = $this->reaction_to_activity($verb);

        if (! $activity) {
            return EMPTY_STR;
        }

        // Check for negative (undo) condition
        // eg: 'Undo/Like' results in $undo conditional and $activity set to 'Like'

        $test = explode('/', $activity);
        if (count($test) > 1) {
            $undo = true;
            $activity = $test[1];
        }

        $is_rsvp = in_array($activity, [ 'Accept', 'Reject', 'TentativeAccept' ]);

        // Check for when target is something besides messages, where argv(1) is the type of thing
        // and  argv(2) is an identifier of things of that type
        // We currently only recognise 'profile' but other types could be handled

        if (argc() == 3) {
            if (! $observer) {
                killme();
            }

            if ($obj_type == 'profile') {
                $r = q(
                    "select * from profile where profile_guid = '%s' limit 1",
                    dbesc($obj_id)
                );

                if (! $r) {
                    killme();
                }

                $profile = array_shift($r);

                $owner_uid = $profile['uid'];

                $public = ((intval($profile['is_default'])) ? true : false);

                // if this is a private profile, select the destination recipients

                if (! $public) {
                    $d = q(
                        "select abook_xchan from abook where abook_profile = '%s' and abook_channel = %d",
                        dbesc($profile['profile_guid']),
                        intval($owner_uid)
                    );
                    if (! $d) {
                        // No profile could be found.
                        killme();
                    }

                    // $d now contains a list of those who can see this profile.
                    // Set the access accordingly.

                    foreach ($d as $dd) {
                        $allow_cid .= '<' . $dd['abook_xchan'] . '>';
                    }
                }

                $post_type = t('channel');
                $objtype = ACTIVITY_OBJ_PROFILE;
            }

            // We'll need the owner of the thing from up above to figure out what channel is the target

            if (! ($owner_uid)) {
                killme();
            }

            // Check permissions of the observer. If this is the owner (mostly this is the case)
            // this will return true for all permissions.

            $perms = get_all_perms($owner_uid, $observer['xchan_hash']);

            if (! ($perms['post_like'] && $perms['view_profile'])) {
                killme();
            }

            $channel = Channel::from_id($owner_uid);

            if (! $channel) {
                killme();
            }

            $object = json_encode(Activity::fetch_profile([ 'id' => Channel::url($channel) ]));

            // second like of the same thing is "undo" for the first like

            $z = q(
                "select * from likes where channel_id = %d and liker = '%s' and verb = '%s' and target_type = '%s' and target_id = '%s' limit 1",
                intval($channel['channel_id']),
                dbesc($observer['xchan_hash']),
                dbesc($activity),
                dbesc(($tgttype) ? $tgttype : $objtype),
                dbesc($obj_id)
            );

            if ($z) {
                $z[0]['deleted'] = 1;
                Libsync::build_sync_packet($channel['channel_id'], array('likes' => $z));

                q(
                    "delete from likes where id = %d",
                    intval($z[0]['id'])
                );
                if ($z[0]['i_mid']) {
                    $r = q(
                        "select id from item where mid = '%s' and uid = %d limit 1",
                        dbesc($z[0]['i_mid']),
                        intval($channel['channel_id'])
                    );
                    if ($r) {
                        drop_item($r[0]['id'], false);
                    }
                }
                killme();
            }
        }

        $uuid = new_uuid();

        $arr = [];

        $arr['uuid']  = $uuid;
        $arr['mid'] = z_root() . (($is_rsvp) ? '/activity/' : '/item/' ) . $uuid;


        $arr['item_thread_top'] = 1;
        $arr['item_origin'] = 1;
        $arr['item_wall'] = 1;


        if ($verb === 'like') {
            $bodyverb = t('%1$s likes %2$s\'s %3$s');
        }
        if ($verb === 'dislike') {
            $bodyverb = t('%1$s doesn\'t like %2$s\'s %3$s');
        }

        if (! isset($bodyverb)) {
            killme();
        }


        $ulink = '[zrl=' . $channel['xchan_url'] . ']' . $channel['xchan_name'] . '[/zrl]';
        $alink = '[zrl=' . $observer['xchan_url'] . ']' . $observer['xchan_name'] . '[/zrl]';
        $plink = '[zrl=' . z_root() . '/profile/' . $channel['channel_address'] . ']' . $post_type . '[/zrl]';
        $private = (($public) ? 0 : 1);


        $arr['aid']          = $channel['channel_account_id'];
        $arr['uid']          = $owner_uid;


        $arr['item_flags']   = $item['item_flags'];
        $arr['item_wall']    = $item['item_wall'];
        $arr['parent_mid']   = $arr['mid'];
        $arr['owner_xchan']  = $channel['xchan_hash'];
        $arr['author_xchan'] = $observer['xchan_hash'];


        $arr['body']          =  sprintf($bodyverb, $alink, $ulink, $plink);

        if ($obj_type === 'profile') {
            if ($public) {
                $arr['body'] .= "\n\n" . '[embed]' . z_root() . '/profile/' . $channel['channel_address'] . '[/embed]';
            } else {
                $arr['body'] .= "\n\n[zmg=80x80]" . $profile['thumb'] . '[/zmg]';
            }
        }


        $arr['verb']          = $activity;
        $arr['obj_type']      = $objtype;
        $arr['obj']           = $object;

        if ($target) {
            $arr['tgt_type']  = $tgttype;
            $arr['target']    = $target;
        }

        $arr['allow_cid']     = $allow_cid;
        $arr['allow_gid']     = $allow_gid;
        $arr['deny_cid']      = $deny_cid;
        $arr['deny_gid']      = $deny_gid;
        $arr['item_private']  = $private;

        Hook::call('post_local', $arr);

        $post = item_store($arr);
        $post_id = $post['item_id'];

        // save the conversation from expiration

        if (local_channel() && array_key_exists('item', $post) && (intval($post['item']['id']) != intval($post['item']['parent']))) {
            retain_item($post['item']['parent']);
        }

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

        $r = q(
            "insert into likes (channel_id,liker,likee,iid,i_mid,verb,target_type,target_id,target) values (%d,'%s','%s',%d,'%s','%s','%s','%s','%s')",
            intval($channel['channel_id']),
            dbesc($observer['xchan_hash']),
            dbesc($channel['channel_hash']),
            intval($post_id),
            dbesc($mid),
            dbesc($activity),
            dbesc(($tgttype) ? $tgttype : $objtype),
            dbesc($obj_id),
            dbesc(($target) ? $target  : $object)
        );
        $r = q(
            "select * from likes where liker = '%s' and likee = '%s' and i_mid = '%s' and verb = '%s' and target_type = '%s' and target_id = '%s' ",
            dbesc($observer['xchan_hash']),
            dbesc($channel['channel_hash']),
            dbesc($mid),
            dbesc($activity),
            dbesc(($tgttype) ? $tgttype : $objtype),
            dbesc($obj_id)
        );
        if ($r) {
            Libsync::build_sync_packet($channel['channel_id'], array('likes' => $r));
        }

        Run::Summon([ 'Notifier', 'like', $post_id ]);

        killme();
    }
}
