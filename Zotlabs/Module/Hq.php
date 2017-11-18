<?php
namespace Zotlabs\Module;

require_once("include/bbcode.php");
require_once('include/security.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');
require_once('include/items.php');


class Hq extends \Zotlabs\Web\Controller {

	function post() {

		if(!local_channel())
			return;

		if($_REQUEST['notify_id']) {
			q("update notify set seen = 1 where id = %d and uid = %d",
				intval($_REQUEST['notify_id']),
				intval(local_channel())
			);
		}

	}

	function get($update = 0, $load = false) {

		if(!local_channel())
			return;

		if($load)
			$_SESSION['loadtime'] = datetime_convert();
	
		if(argc() > 1 && argv(1) !== 'load') {
			$item_hash = argv(1);
		}
	
		if($_REQUEST['mid'])
			$item_hash = $_REQUEST['mid'];

		if(! $item_hash) {

			$r = q("SELECT mid FROM item
				WHERE uid = %d
				AND mid = parent_mid
				$item_normal
				ORDER BY id DESC
				limit 1",
				local_channel()
			);
			$item_hash = 'b64.' . base64url_encode($r[0]['mid']);

			if(!$item_hash) {
				\App::$error = 404;
				notice( t('Item not found.') . EOL);
				return;
			}
		}
	
		$updateable = false;

		if(! $update) {
	
			$channel = \App::get_channel();

			$channel_acl = [
				'allow_cid' => $channel['channel_allow_cid'], 
				'allow_gid' => $channel['channel_allow_gid'], 
				'deny_cid'  => $channel['channel_deny_cid'], 
				'deny_gid'  => $channel['channel_deny_gid']
			]; 

			$x = [
				'is_owner'            => true,
				'allow_location'      => ((intval(get_pconfig($channel['channel_id'],'system','use_browser_location'))) ? '1' : ''),
				'default_location'    => $channel['channel_location'],
				'nickname'            => $channel['channel_address'],
				'lockstate'           => (($group || $cid || $channel['channel_allow_cid'] || $channel['channel_allow_gid'] || $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
	
				'acl'                 => populate_acl($channel_acl),
				'permissions'         => $channel_acl,
				'bang'                => '',
				'visitor'             => true,
				'profile_uid'         => local_channel(),
				'return_path'         => 'channel/' . $channel['channel_address'],
				'expanded'            => true,
				'editor_autocomplete' => true,
				'bbco_autocomplete'   => 'bbcode',
				'bbcode'              => true,
				'jotnets'             => true
			];
	
			$o = '<div id="jot-popup">';
			$o .= status_editor($a,$x);
			$o .= '</div>';
		}
	
		$target_item = null;

		if(strpos($item_hash,'b64.') === 0)
			$decoded = @base64url_decode(substr($item_hash,4));
		if($decoded)
			$item_hash = $decoded;

		$r = q("select id, uid, mid, parent_mid, thr_parent, verb, item_type, item_deleted, item_blocked from item where mid like '%s' limit 1",
			dbesc($item_hash . '%')
		);
	
		if($r) {
			$target_item = $r[0];
		}

		//if the item is to be moderated redirect to /moderate
		if($target_item['item_blocked'] == ITEM_MODERATED) {
			goaway(z_root() . '/moderate/' . $target_item['id']);
		}
	
		$static = ((array_key_exists('static',$_REQUEST)) ? intval($_REQUEST['static']) : 0);

		$simple_update = (($update) ? " AND item_unseen = 1 " : '');
			
		if($update && $_SESSION['loadtime'])
			$simple_update = " AND (( item_unseen = 1 AND item.changed > '" . datetime_convert('UTC','UTC',$_SESSION['loadtime']) . "' )  OR item.changed > '" . datetime_convert('UTC','UTC',$_SESSION['loadtime']) . "' ) ";
		if($load)
			$simple_update = '';
	
		if($static && $simple_update)
			$simple_update .= " and item_thread_top = 0 and author_xchan = '" . protect_sprintf(get_observer_hash()) . "' ";
	
		if(! $update && ! $load) {

			$static  = ((local_channel()) ? channel_manual_conv_update(local_channel()) : 1);

			// if the target item is not a post (eg a like) we want to address its thread parent

			$mid = ((($target_item['verb'] == ACTIVITY_LIKE) || ($target_item['verb'] == ACTIVITY_DISLIKE)) ? $target_item['thr_parent'] : $target_item['mid']);

			// if we got a decoded hash we must encode it again before handing to javascript 
			if($decoded)
				$mid = 'b64.' . base64url_encode($mid);

			$o .= '<div id="live-display"></div>' . "\r\n";
			$o .= "<script> var profile_uid = " . local_channel()
				. "; var netargs = '?f='; var profile_page = " . \App::$pager['page'] . "; </script>\r\n";
	
			\App::$page['htmlhead'] .= replace_macros(get_markup_template("build_query.tpl"),[
				'$baseurl' => z_root(),
				'$pgtype'  => 'display',
				'$uid'     => '0',
				'$gid'     => '0',
				'$cid'     => '0',
				'$cmin'    => '0',
				'$cmax'    => '99',
				'$star'    => '0',
				'$liked'   => '0',
				'$conv'    => '0',
				'$spam'    => '0',
				'$fh'      => '0',
				'$nouveau' => '0',
				'$wall'    => '0',
				'$static'  => $static,
				'$page'    => 1,
				'$list'    => ((x($_REQUEST,'list')) ? intval($_REQUEST['list']) : 0),
				'$search'  => '',
				'$xchan'   => '',
				'$order'   => '',
				'$file'    => '',
				'$cats'    => '',
				'$tags'    => '',
				'$dend'    => '',
				'$dbegin'  => '',
				'$verb'    => '',
				'$net'     => '',
				'$mid'     => $mid
			]);

		}

		$item_normal = item_normal();
		$item_normal_update = item_normal_update();

		if($load) {
			$r = null;

			$r = q("SELECT item.id as item_id from item
				WHERE uid = %d
				and mid = '%s'
				$item_normal
				limit 1",
				intval(local_channel()),
				dbesc($target_item['parent_mid'])
			);
			if($r) {
				$updateable = true;
			}

		}
	
		elseif($update) {
			$r = null;

			$r = q("SELECT item.parent AS item_id from item
				WHERE uid = %d
				and parent_mid = '%s'
				$item_normal_update
				$simple_update
				limit 1",
				intval(local_channel()),
				dbesc($target_item['parent_mid'])
			);
			if($r) {
				$updateable = true;
			}

			$_SESSION['loadtime'] = datetime_convert();
		}
	
		else {
			$r = [];
		}
	
		if($r) {
			$parents_str = ids_to_querystr($r,'item_id');
			if($parents_str) {
				$items = q("SELECT item.*, item.id AS item_id 
					FROM item
					WHERE parent in ( %s ) $item_normal ",
					dbesc($parents_str)
				);
	
				xchan_query($items);
				$items = fetch_post_tags($items,true);
				$items = conv_sort($items,'created');
			}
		}
		else {
			$items = [];
		}
	
		$o .= conversation($items, 'display', $update, 'client');

		if($updateable) {
			$x = q("UPDATE item SET item_unseen = 0 where item_unseen = 1 AND uid = %d and parent = %d ",
				intval(local_channel()),
				intval($r[0]['item_id'])
			);
		}

		$o .= '<div id="content-complete"></div>';

		if(($update && $load) && (! $items))  {
			notice( t('Something went wrong.') . EOL );
		}

		return $o;

	}

}
