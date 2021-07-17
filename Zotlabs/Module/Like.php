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

		$undo = false;

		$acts = [
			'like'        => 'Like',
			'dislike'     => 'Dislike',
			'attendyes'   => 'Accept',
			'attendno'    => 'Reject',
			'attendmaybe' => 'TentativeAccept', 
		];

		// unlike (etc.) reactions are an undo of positive reactions, rather than a negative action.
		// The activity is the same in undo actions and will have the same activity mapping

		if(substr($reaction,0,2) === 'un') {
			$undo = true;
			$reaction = substr($reaction,2);
		}

		if(array_key_exists($reaction,$acts)) {
			return (($undo) ? 'Undo/' : EMPTY_STR) . $acts[$reaction];
		}

		return EMPTY_STR;

	}



	public function get() {

		$undo = false;
		$object = $target = null;
		$owner_uid = 0;
		$post_type = EMPTY_STR;
		$objtype = EMPTY_STR;
		$allow_cid = $allow_gid = $deny_cid = $deny_gid = '';
		$output = EMPTY_STR;

		$sys_channel = get_sys_channel();
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
		
		
		if (! $observer) {
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

		$item = array_shift($r);

		$owner_uid = $item['uid'];
		$owner_aid = $item['aid'];

        $can_comment = false;
		if ((array_key_exists('owner',$item)) && intval($item['owner']['abook_self'])) {
			$can_comment = perm_is_allowed($item['uid'],$observer['xchan_hash'],'post_comments');
		}
		else {
			$can_comment = can_comment_on_post($observer['xchan_hash'],$item);
		}

		if (! $can_comment) {
			notice( t('Permission denied') . EOL);
			killme();
		}

		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($item['owner_xchan'])
		);

		if ($r) {
			$thread_owner = array_shift($r);
		}
		else {
			killme();
		}
		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($item['author_xchan'])
		);
		if ($r) {
			$item_author = array_shift($r);
		}
		else {
			killme();
		}
			
		$verbs = " '" . dbesc($activity)  . "' ";
	
	
		$uuid = new_uuid();
	
		$arr = [];

		$arr['uuid']  = $uuid;
        $arr['mid'] = z_root() . (($is_rsvp) ? '/activity/' : '/item/' ) . $uuid;

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
	
		if (intval($item['item_hidden'])) {
			$r = q("update item set item_hidden = 0 where id = %d",
				intval($item['id'])
			);

			$r = q("select * from item where id = %d",
				intval($item['id'])
			);
			if ($r) {
				xchan_query($r);
				$sync_item = fetch_post_tags($r);
				Libsync::build_sync_packet($channel['channel_id'], [ 'item' => [ encode_item($sync_item[0],true) ] ]);
			}

			Run::Summon( [ 'Notifier','wall-new',$item['id'] ] );
		}	
	
	
		if ($activity === 'Like') {
			$bodyverb = t('%1$s likes %2$s\'s %3$s');
		}
		if ($activity === 'Dislike') {
			$bodyverb = t('%1$s doesn\'t like %2$s\'s %3$s');
		}
		if ($activity === 'Accept') {
			$bodyverb = t('%1$s is attending %2$s\'s %3$s');
		}
		if ($activity === 'Reject') {
			$bodyverb = t('%1$s is not attending %2$s\'s %3$s');
		}
		if ($activity === 'tentativeAccept') {
			$bodyverb = t('%1$s may attend %2$s\'s %3$s');
		}
	
		if (! isset($bodyverb)) {
			killme(); 
		}


		if (local_channel() && $activity === 'Accept') {
			event_addtocal($item['id'],$channel['channel_id']);
		}
		
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
		
		$arr['aid']          = $owner_aid;
		$arr['uid']          = $owner_uid;

		$arr['item_flags']   = $item['item_flags'];
		$arr['item_wall']    = $item['item_wall'];
		$arr['parent_mid']   = $item['mid'];
		$arr['owner_xchan']  = $thread_owner['xchan_hash'];
		$arr['author_xchan'] = $observer['xchan_hash'];
	
		
		$arr['body']          =  sprintf( $bodyverb, $alink, $ulink, $plink );
	
		$arr['verb']          = (($undo) ? 'Undo' : $activity);
		$arr['obj_type']      = (($undo) ? $activity : $objtype);
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

		if (local_channel() && array_key_exists('item',$post) && (intval($post['item']['id']) != intval($post['item']['parent']))) {
			retain_item($post['item']['parent']);
		}
	
		$arr['id'] = $post_id;
	
		call_hooks('post_local_end', $arr);
	
		$r = q("select * from item where id = %d",
			intval($post_id)
		);
		if ($r) {
			xchan_query($r);
			$sync_item = fetch_post_tags($r);
			Libsync::build_sync_packet($channel['channel_id'], [ 'item' => [ encode_item($sync_item[0],true) ] ]);
		}

		Run::Summon( [ 'Notifier', 'like', $post_id ] );
	
		killme();
	}
	
	
	
}
