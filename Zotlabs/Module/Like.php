<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Activity;
use Zotlabs\Web\Controller;
use Zotlabs\Daemon\Run;

require_once('include/security.php');
require_once('include/bbcode.php');
require_once('include/event.php');

class Like extends Controller {

	private function reaction_to_activity($reaction) {

		$acts = [
			'like'        => ACTIVITY_LIKE ,
			'dislike'     => ACTIVITY_DISLIKE,
			'attendyes'   => 'Accept',
			'attendno'    => 'Reject',
			'attendmaybe' => 'TentativeAccept', 
		];

		// unlike (etc.) reactions are an undo of positive reactions, rather than a negative action.
		// The activity is the same in undo actions and will have the same activity mapping

		if(substr($reaction,0,2) === 'un') {
			$reaction = substr($reaction,2);
		}

		if(array_key_exists($reaction,$acts)) {
			return $acts[$reaction];
		}

		return EMPTY_STR;

	}



	public function get() {
	
		$o = EMPTY_STR;

		$sys_channel = get_sys_channel();
		$sys_channel_id = (($sys_channel) ? $sys_channel['channel_id'] : 0);

		$observer = App::get_observer();
	
		$verb = notags(trim($_GET['verb']));
	
		if (! $verb) {
			$verb = 'like';
		}
		
		$activity = $this->reaction_to_activity($verb);

		if (! $activity) {
			return EMPTY_STR; 
		}

		$is_rsvp = false;
		
		if (in_array($activity, [ 'Accept', 'Reject', 'TentativeAccept' ])) {
			$is_rsvp = true;
		}



		$extended_like = false;
		$object = $target = null;
		$post_type = EMPTY_STR;
		$objtype = EMPTY_STR;
	
		if(argc() == 3) {
	
			if(! $observer)
				killme();
	
			$extended_like = true;
			$obj_type = argv(1);
			$obj_id = argv(2);
			$public = true;
	
			if($obj_type == 'profile') {
				$r = q("select * from profile where profile_guid = '%s' limit 1",
					dbesc(argv(2))
				);
				if(! $r)
					killme();			
				$owner_uid = $r[0]['uid'];
				if($r[0]['is_default'])
					$public = true;
				if(! $public) {
					$d = q("select abook_xchan from abook where abook_profile = '%s' and abook_channel = %d",
						dbesc($r[0]['profile_guid']),
						intval($owner_uid)
					);
					if(! $d) {
						// forgery - illegal
						if($interactive) {
							notice( t('Invalid request.') . EOL);
							return $o;
						}
						killme();
					}
					// $d now contains a list of those who can see this profile - only send the status notification
					// to them.
					$allow_cid = $allow_gid = $deny_cid = $deny_gid = '';
					foreach($d as $dd) {
						$allow_cid .= '<' . $dd['abook_xchan'] . '>';
					}
				}
				$post_type = t('channel');
				$objtype = ACTIVITY_OBJ_PROFILE;
	
				$profile = $r[0];
			}
			elseif($obj_type == 'thing') {
	
				$r = q("select * from obj where obj_type = %d and obj_obj = '%s' limit 1",
	            	intval(TERM_OBJ_THING),
	            	dbesc(argv(2))
	        	);
	
				if(! $r) {
					if($interactive) {
						notice( t('Invalid request.') . EOL);
						return $o;
					}
					killme();		
				}
	
				$owner_uid = $r[0]['obj_channel'];
	
				$allow_cid = $r[0]['allow_cid'];
				$allow_gid = $r[0]['allow_gid'];
				$deny_cid = $r[0]['deny_cid'];
				$deny_gid = $r[0]['deny_gid'];
				if($allow_cid || $allow_gid || $deny_cid || $deny_gid)			
					$public = false;
	
				$post_type = t('thing');
				$objtype = ACTIVITY_OBJ_PROFILE;
				$tgttype = ACTIVITY_OBJ_THING;
	
				$links   = [];
				$links[] = array('rel' => 'alternate', 'type' => 'text/html',
					'href' => z_root() . '/thing/' . $r[0]['obj_obj']);
				if($r[0]['imgurl'])	
					$links[] = array('rel' => 'photo', 'href' => $r[0]['obj_imgurl']);
	
				$target = json_encode(array(
					'type'  => $tgttype,
					'title' => $r[0]['obj_term'],
					'id'    => z_root() . '/thing/' . $r[0]['obj_obj'],
					'link'  => $links
				));
	
				$plink = '[zrl=' . z_root() . '/thing/' . $r[0]['obj_obj'] . ']' . $r[0]['obj_term'] . '[/zrl]';
	
			}
			
			if (! ($owner_uid && $r)) {
				killme();
			}
	
			// The resultant activity is going to be a wall-to-wall post, so make sure this is allowed
	
			$perms = get_all_perms($owner_uid,$observer['xchan_hash']);
	
			if(! ($perms['post_like'] && $perms['view_profile'])) {
				killme();
			}
	
			$ch = q("select * from channel left join xchan on channel_hash = xchan_hash where channel_id = %d limit 1",
				intval($owner_uid)
			);
			if(! $ch) {
				killme();
			}
				
			if(! $plink)
				$plink = '[zrl=' . z_root() . '/profile/' . $ch[0]['channel_address'] . ']' . $post_type . '[/zrl]';
		
	
			$object = json_encode(Activity::fetch_profile([ 'id' => channel_url($ch[0]) ]));

			// second like of the same thing is "undo" for the first like
	
			$z = q("select * from likes where channel_id = %d and liker = '%s' and verb = '%s' and target_type = '%s' and target_id = '%s' limit 1",
				intval($ch[0]['channel_id']),
				dbesc($observer['xchan_hash']),
				dbesc($activity),
				dbesc(($tgttype)?$tgttype:$objtype),
				dbesc($obj_id)
			);
	
			if($z) {
				$z[0]['deleted'] = 1;
				Libsync::build_sync_packet($ch[0]['channel_id'],array('likes' => $z));
	
				q("delete from likes where id = %d",
					intval($z[0]['id'])
				);
				if($z[0]['i_mid']) {
					$r = q("select id from item where mid = '%s' and uid = %d limit 1",
						dbesc($z[0]['i_mid']),
						intval($ch[0]['channel_id'])
					);
					if($r)
						drop_item($r[0]['id'],false);

				}
				killme();
			}
		}
		else {
	
			if(! $observer) {
				killme();
			}

			// this is used to like an item or comment
	
			$item_id = ((argc() == 2) ? notags(trim(argv(1))) : 0);
	
			logger('like: verb ' . $verb . ' item ' . $item_id, LOGGER_DEBUG);
	
			// get the item. Allow linked photos (which are normally hidden) to be liked

			$r = q("SELECT * FROM item WHERE id = %d 
				and item_type in (0,6,7) and item_deleted = 0 and item_unpublished = 0 
				and item_delayed = 0 and item_pending_remove = 0 and item_blocked = 0 LIMIT 1",
				intval($item_id)
			);

			// if interacting with a pubstream item,
			// create a copy of the parent in your stream.

			if ($r) {
				if (local_channel()) {
					$r = [ copy_of_pubitem(App::get_channel(), $r[0]['mid']) ];
				}
			}

			if(! $item_id || (! $r)) {
				logger('like: no item ' . $item_id);
				killme();
			}

			xchan_query($r,true);

			$item = $r[0];

			$owner_uid = $r[0]['uid'];
			$owner_aid = $r[0]['aid'];

            $can_comment = false;
            if((array_key_exists('owner',$item)) && intval($item['owner']['abook_self']))
                $can_comment = perm_is_allowed($item['uid'],$observer['xchan_hash'],'post_comments');
            else
                $can_comment = can_comment_on_post($observer['xchan_hash'],$item);

            if(! $can_comment) {
				notice( t('Permission denied') . EOL);
				killme();
			}

			$r = q("select * from xchan where xchan_hash = '%s' limit 1",
				dbesc($item['owner_xchan'])
			);

			if($r)
				$thread_owner = $r[0];
			else
				killme();
	
			$r = q("select * from xchan where xchan_hash = '%s' limit 1",
				dbesc($item['author_xchan'])
			);
			if($r)
				$item_author = $r[0];
			else
				killme();
	
			
			$verbs = " '".dbesc($activity)."' ";
	
			$multi_undo = false;		
	
			$item_normal = " and item.item_deleted = 0 and item.item_unpublished = 0 and item.item_delayed = 0
				and item.item_pending_remove = 0 and item.item_blocked = 0 and item.obj_type != '" . ACTIVITY_OBJ_FILE . "' ";

			$r = q("SELECT id, parent, uid, verb FROM item WHERE verb in ( $verbs ) $item_normal
				AND author_xchan = '%s' AND thr_parent = '%s' and uid = %d ",
				dbesc($observer['xchan_hash']),
				dbesc($item['mid']),
				intval($owner_uid)
			);
			if($r) {
				// already liked it. Drop that item.
				foreach($r as $rr) {
					drop_item($rr['id'],false,DROPITEM_PHASE1);
					// set the changed timestamp on the parent so we'll see the update without a page reload
					$z = q("update item set changed = '%s' where id = %d and uid = %d",
						dbesc(datetime_convert()),
						intval($rr['parent']),
						intval($rr['uid'])
					);
					// Prior activity was a duplicate of the one we're submitting, just undo it; 
					// don't fall through and create another
					if(activity_match($rr['verb'],$activity))
						$multi_undo = false;
	
					// drop_item was not done interactively, so we need to invoke the notifier
					// in order to push the changes to connections
	
					Run::Summon( [ 'Notifier','drop',$rr['id'] ] );
	
				}
	
	
				if(! $multi_undo)
					killme();
			}
		}
	
		$uuid = new_uuid();
	
		$arr = [];

		$arr['uuid']  = $uuid;
        $arr['mid'] = z_root() . (($is_rsvp) ? '/activity/' : '/item/' ) . $uuid;

	
		if($extended_like) {
			$arr['item_thread_top'] = 1;
			$arr['item_origin'] = 1;
			$arr['item_wall'] = 1;
		}
		else {
			$post_type = (($item['resource_type'] === 'photo') ? t('photo') : t('status'));
			if ($item['obj_type'] === ACTIVITY_OBJ_EVENT) {
				$post_type = t('event');
			}
	
			$objtype = $item['obj_type'];

			$body = $item['body'];
	
			$object = json_encode(Activity::fetch_item( [ 'id' => $item['mid'] ]));
	
			if (! intval($item['item_thread_top'])) {
				$post_type = 'comment';
			}
	
			$arr['item_origin'] = 1;
			$arr['item_notshown'] = 1;
			$arr['item_type'] = $item['item_type'];
	
			if (intval($item['item_wall'])) {
				$arr['item_wall'] = 1;
			}
	
			// if this was a linked photo and was hidden, unhide it and distribute it.
	
			if(intval($item['item_hidden'])) {
				$r = q("update item set item_hidden = 0 where id = %d",
					intval($item['id'])
				);

				$r = q("select * from item where id = %d",
					intval($item['id'])
				);
				if($r) {
					xchan_query($r);
					$sync_item = fetch_post_tags($r);
					Libsync::build_sync_packet($ch[0]['channel_id'], [ 'item' => [ encode_item($sync_item[0],true) ] ]);
				}

				Run::Summon( [ 'Notifier','wall-new',$item['id'] ] );
			}	
	
		}
	
		if($verb === 'like')
			$bodyverb = t('%1$s likes %2$s\'s %3$s');
		if($verb === 'dislike')
			$bodyverb = t('%1$s doesn\'t like %2$s\'s %3$s');
		if($verb === 'attendyes') 
			$bodyverb = t('%1$s is attending %2$s\'s %3$s');
		if($verb === 'attendno')
			$bodyverb = t('%1$s is not attending %2$s\'s %3$s');
		if($verb === 'attendmaybe')
			$bodyverb = t('%1$s may attend %2$s\'s %3$s');
	
		if (! isset($bodyverb)) {
			killme(); 
		}


		if (local_channel() && $verb === 'attendyes') {
			event_addtocal($item['id'],$ch[0]['channel_id']);
		}
		
		if($extended_like) {
			$ulink = '[zrl=' . $ch[0]['xchan_url'] . ']' . $ch[0]['xchan_name'] . '[/zrl]';
			$alink = '[zrl=' . $observer['xchan_url'] . ']' . $observer['xchan_name'] . '[/zrl]';
			$private = (($public) ? 0 : 1);
		}
		else {
			$arr['parent']       = $item['id'];
			$arr['thr_parent']   = $item['mid'];
			$ulink = '[zrl=' . $item_author['xchan_url'] . ']' . $item_author['xchan_name'] . '[/zrl]';
			$alink = '[zrl=' . $observer['xchan_url'] . ']' . $observer['xchan_name'] . '[/zrl]';
			$plink = '[zrl=' . z_root() . '/display/' . gen_link_id($item['mid']) . ']' . $post_type . '[/zrl]';
			$allow_cid       = $item['allow_cid'];
			$allow_gid       = $item['allow_gid'];
			$deny_cid        = $item['deny_cid'];
			$deny_gid        = $item['deny_gid'];
			$private         = $item['private'];
	
		}
		
	
		$arr['aid']          = (($extended_like) ? $ch[0]['channel_account_id'] : $owner_aid);
		$arr['uid']          = $owner_uid;


		$arr['item_flags']   = $item['item_flags'];
		$arr['item_wall']    = $item['item_wall'];
		$arr['parent_mid']   = (($extended_like) ? $arr['mid'] : $item['mid']);
		$arr['owner_xchan']  = (($extended_like) ? $ch[0]['xchan_hash'] : $thread_owner['xchan_hash']);
		$arr['author_xchan'] = $observer['xchan_hash'];
	
		
		$arr['body']          =  sprintf( $bodyverb, $alink, $ulink, $plink );
		if($obj_type === 'thing' && $r[0]['imgurl']) {
			$arr['body'] .= "\n\n[zmg=80x80]" . $r[0]['imgurl'] . '[/zmg]';
		}	
		if($obj_type === 'profile') {
			if($public) {
				$arr['body'] .= "\n\n" . '[embed]' . z_root() . '/profile/' . $ch[0]['channel_address'] . '[/embed]';	
			}
			else
				$arr['body'] .= "\n\n[zmg=80x80]" . $profile['thumb'] . '[/zmg]';
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
	
		call_hooks('post_local',$arr);
	
		$post = item_store($arr);	
		$post_id = $post['item_id'];

		// save the conversation from expiration

		if(local_channel() && array_key_exists('item',$post) && (intval($post['item']['id']) != intval($post['item']['parent'])))
			retain_item($post['item']['parent']); 
	
		$arr['id'] = $post_id;
	
		call_hooks('post_local_end', $arr);
	
		$r = q("select * from item where id = %d",
			intval($post_id)
		);
		if($r) {
			xchan_query($r);
			$sync_item = fetch_post_tags($r);
			Libsync::build_sync_packet($ch[0]['channel_id'], [ 'item' => [ encode_item($sync_item[0],true) ] ]);
		}

		if($extended_like) {
			$r = q("insert into likes (channel_id,liker,likee,iid,i_mid,verb,target_type,target_id,target) values (%d,'%s','%s',%d,'%s','%s','%s','%s','%s')",
				intval($ch[0]['channel_id']),
				dbesc($observer['xchan_hash']),
				dbesc($ch[0]['channel_hash']),
				intval($post_id),
				dbesc($mid),
				dbesc($activity),
				dbesc(($tgttype)? $tgttype : $objtype),
				dbesc($obj_id),
				dbesc(($target) ? $target  : $object)
			);
			$r = q("select * from likes where liker = '%s' and likee = '%s' and i_mid = '%s' and verb = '%s' and target_type = '%s' and target_id = '%s' ",
				dbesc($observer['xchan_hash']),
				dbesc($ch[0]['channel_hash']),
				dbesc($mid),
				dbesc($activity),
				dbesc(($tgttype)? $tgttype : $objtype),
				dbesc($obj_id)
			);
			if($r)
				Libsync::build_sync_packet($ch[0]['channel_id'],array('likes' => $r));	
	
		}
	
	
		Run::Summon( [ 'Notifier', 'like', $post_id ] );
	
		killme();
	}
	
	
	
}
