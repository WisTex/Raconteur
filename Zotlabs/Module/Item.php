<?php

namespace Zotlabs\Module;

/**
 *
 * As a GET request, this module answers to activitypub and zot6 item fetches and
 * acts as a permalink for local content.
 *
 * Otherwise this is the POST destination for most all locally posted
 * text stuff. This function handles status, wall-to-wall status, 
 * local comments, and remote coments that are posted on this site 
 * (as opposed to being delivered in a feed).
 * Also processed here are posts and comments coming through the API. 
 * All of these become an "item" which is our basic unit of 
 * information.
 * Posts that originate externally or do not fall into the above 
 * posting categories go through item_store() instead of this function. 
 *
 */  

use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\ThreadListener;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\IConfig;
use Zotlabs\Lib\Enotify;
use Zotlabs\Lib\Apps;
use Zotlabs\Access\PermissionLimits;
use Zotlabs\Access\PermissionRoles;
use Zotlabs\Access\AccessControl;
use Zotlabs\Daemon\Run;
use App;
use URLify;

require_once('include/attach.php');
require_once('include/bbcode.php');
require_once('include/security.php');


use Zotlabs\Lib as Zlib;

class Item extends Controller {

	function init() {


		if (ActivityStreams::is_as_request()) {
			$item_id = argv(1);
			if (! $item_id) {
				http_status_exit(404, 'Not found');
			}
			$portable_id = EMPTY_STR;

			$item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_blocked = 0 ";

			$i = null;

			// do we have the item (at all)?
			// add preferential bias to item owners (item_wall = 1)

			$r = q("select * from item where (mid = '%s' or uuid = '%s') $item_normal order by item_wall desc limit 1",
				dbesc(z_root() . '/item/' . $item_id),
				dbesc($item_id)
			);

			if (! $r) {
				http_status_exit(404,'Not found');
			}

			// process an authenticated fetch


			$sigdata = HTTPSig::verify(EMPTY_STR);
			if ($sigdata['portable_id'] && $sigdata['header_valid']) {
				$portable_id = $sigdata['portable_id'];
				if (! check_channelallowed($portable_id)) {
					http_status_exit(403, 'Permission denied');
				}
				if (! check_siteallowed($sigdata['signer'])) {
					http_status_exit(403, 'Permission denied');
				}
				observer_auth($portable_id);

				$i = q("select id as item_id from item where mid = '%s' $item_normal and owner_xchan = '%s' limit 1 ",
					dbesc($r[0]['parent_mid']),
					dbesc($portable_id)
				);
			}
			elseif (Config::get('system','require_authenticated_fetch',false)) {
				http_status_exit(403,'Permission denied');
			}

			// if we don't have a parent id belonging to the signer see if we can obtain one as a visitor that we have permission to access
			// with a bias towards those items owned by channels on this site (item_wall = 1)

			$sql_extra = item_permissions_sql(0);

			if (! $i) {
				$i = q("select id as item_id from item where mid = '%s' $item_normal $sql_extra order by item_wall desc limit 1",
					dbesc($r[0]['parent_mid'])
				);
			}

			if (! $i) {
				http_status_exit(403,'Forbidden');
			}

			// If we get to this point we have determined we can access the original in $r (fetched much further above), so use it.

			xchan_query($r,true);
			$items = fetch_post_tags($r,false);

			$chan = channelx_by_n($items[0]['uid']);

			if (! $chan) {
				http_status_exit(404, 'Not found');
			}
			
			if (! perm_is_allowed($chan['channel_id'],get_observer_hash(),'view_stream')) {
				http_status_exit(403, 'Forbidden');
			}

			$i = Activity::encode_item($items[0],true);

			if (! $i) {
				http_status_exit(404, 'Not found');
			}
			
			if ($portable_id && (! intval($items[0]['item_private']))) {
				$c = q("select abook_id from abook where abook_channel = %d and abook_xchan = '%s'",
					intval($items[0]['uid']),
					dbesc($portable_id)
				);
				if (! $c) {
					ThreadListener::store(z_root() . '/item/' . $item_id,$portable_id);
				}
			}
			
			as_return_and_die($i,$chan);
		}

		if (Libzot::is_zot_request()) {

			$conversation = false;

			$item_id = argv(1);

			if (! $item_id) {
				http_status_exit(404, 'Not found');
			}
			$portable_id = EMPTY_STR;

			$item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_blocked = 0 and not verb in ( 'Follow', 'Unfollow' ) ";

			$i = null;

			// do we have the item (at all)?

			$r = q("select * from item where (mid = '%s' or uuid = '%s') $item_normal limit 1",
				dbesc(z_root() . '/item/' . $item_id),
				dbesc($item_id)
			);

			if (! $r) {
				http_status_exit(404,'Not found');
			}

			// process an authenticated fetch

			$sigdata = HTTPSig::verify(($_SERVER['REQUEST_METHOD'] === 'POST') ? file_get_contents('php://input') : EMPTY_STR);
			if ($sigdata['portable_id'] && $sigdata['header_valid']) {
				$portable_id = $sigdata['portable_id'];
				if (! check_channelallowed($portable_id)) {
					http_status_exit(403, 'Permission denied');
				}
				if (! check_siteallowed($sigdata['signer'])) {
					http_status_exit(403, 'Permission denied');
				}
				observer_auth($portable_id);

				$i = q("select id as item_id from item where mid = '%s' $item_normal and owner_xchan = '%s' limit 1",
					dbesc($r[0]['parent_mid']),
					dbesc($portable_id)
				);
			}
			elseif (Config::get('system','require_authenticated_fetch',false)) {
				http_status_exit(403,'Permission denied');
			}

			// if we don't have a parent id belonging to the signer see if we can obtain one as a visitor that we have permission to access
			// with a bias towards those items owned by channels on this site (item_wall = 1)
			
			$sql_extra = item_permissions_sql(0);

			if (! $i) {
				$i = q("select id as item_id from item where mid = '%s' $item_normal $sql_extra order by item_wall desc limit 1",
					dbesc($r[0]['parent_mid'])
				);
			}

			if (! $i) {
				http_status_exit(403,'Forbidden');
			}

			$parents_str = ids_to_querystr($i,'item_id');
	
			$items = q("SELECT item.*, item.id AS item_id FROM item WHERE item.parent IN ( %s ) $item_normal order by item.id asc",
				dbesc($parents_str)
			);

			if (! $items) {
				http_status_exit(404, 'Not found');
			}

			xchan_query($items,true);
			$items = fetch_post_tags($items,true);

			if (! $items) {
				http_status_exit(404, 'Not found');
			}
			$chan = channelx_by_n($items[0]['uid']);

			if (! $chan) {
				http_status_exit(404, 'Not found');
			}
			
			if (! perm_is_allowed($chan['channel_id'],get_observer_hash(),'view_stream')) {
				http_status_exit(403, 'Forbidden');
			}

			$i = Activity::encode_item_collection($items,'conversation/' . $item_id,'OrderedCollection',true, count($nitems));
			if ($portable_id && (! intval($items[0]['item_private']))) {
				ThreadListener::store(z_root() . '/item/' . $item_id,$portable_id);
			}

			if (! $i) {
				http_status_exit(404, 'Not found');
			}
			$x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				Activity::ap_schema()
				]], $i);

			$headers = [];
			$headers['Content-Type'] = 'application/x-zot+json' ;
			$x['signature'] = LDSignatures::sign($x,$chan);
			$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
			$headers['Digest'] = HTTPSig::generate_digest_header($ret);
			$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];
			$h = HTTPSig::create_sig($headers,$chan['channel_prvkey'],channel_url($chan));
			HTTPSig::set_headers($h);
			echo $ret;
			killme();

		}

		// if it isn't a drop command and isn't a post method and wasn't handled already,
		// the default action is a browser request for a persistent uri and this should return
		// the text/html page of the item.
		
		if (argc() > 1 && argv(1) !== 'drop') {
			$x = q("select uid, item_wall, llink, mid from item where mid = '%s' or mid = '%s' or uuid = '%s'",
				dbesc(z_root() . '/item/' . argv(1)),
				dbesc(z_root() . '/activity/' . argv(1)),
				dbesc(argv(1))
			);
			if ($x) {
				foreach ($x as $xv) {
					if (intval($xv['item_wall'])) {
						$c = channelx_by_n($xv['uid']);
						if ($c) {
							goaway($c['xchan_url'] . '?mid=' . gen_link_id($xv['mid']));
						}
					}
				}
				goaway($x[0]['llink']);
			}
			http_status_exit(404, 'Not found');
		}
	}

	function post() {

		if ((! local_channel()) && (! remote_channel()) && (! isset($_REQUEST['anonname']))) {
			return;
		}

		// drop an array of items.
		
		if (isset($_REQUEST['dropitems'])) {
			$arr_drop = explode(',',$_REQUEST['dropitems']);
			drop_items($arr_drop);
			$json = array('success' => 1);
			echo json_encode($json);
			killme();
		}


		$uid = local_channel();
		$channel  = null;
		$observer = null;
		$token    = EMPTY_STR;
		$datarray = [];
		$item_starred = false;
		$item_uplink = false;
		$item_notshown = false;
		$item_nsfw = false;
		$item_relay = false;
		$item_mentionsme = false;
		$item_verified = false;
		$item_retained = false;
		$item_rss = false;
		$item_deleted = false;
		$item_hidden = false;
		$item_delayed = false;
		$item_pending_remove = false;
		$item_blocked = false;

		$post_tags = false;





		/**
		 * Is this a reply to something?
		 */
	
		$parent     = ((isset($_REQUEST['parent']))     ? intval($_REQUEST['parent'])   : 0);
		$parent_mid = ((isset($_REQUEST['parent_mid'])) ? trim($_REQUEST['parent_mid']) : '');
	
		$hidden_mentions = ((isset($_REQUEST['hidden_mentions'])) ? trim($_REQUEST['hidden_mentions']) : '');


		/**
		 * Who is viewing this page and posting this thing
		 */
		 
		$remote_xchan = ((isset($_REQUEST['remote_xchan'])) ? trim($_REQUEST['remote_xchan']) : false);
		$remote_observer = xchan_match( ['xchan_hash' => $remote_xchan ] );

		if (! $remote_observer) {
			$remote_xchan = $remote_observer = false;
		}

		// This is the local channel representing who the posted item will belong to.

		$profile_uid = ((isset($_REQUEST['profile_uid'])) ? intval($_REQUEST['profile_uid'])    : 0);

		// *If* you are logged in as the site admin you are allowed to create top-level items for the sys channel.
		// This would typically be a webpage or webpage element.
		// Comments and replies are excluded because further below we also check for sys channel ownership and
		// will make a copy of the parent that you can interact with in your own stream
		
		$sys = get_sys_channel();
		if ($sys && $profile_uid && ($sys['channel_id'] == $profile_uid) && is_site_admin() && ! $parent) {
			$uid = intval($sys['channel_id']);
			$channel = $sys;
			$observer = $sys;
		}

		call_hooks('post_local_start', $_REQUEST);
	
		// logger('postvars ' . print_r($_REQUEST,true), LOGGER_DATA);
	
		$api_source = ((isset($_REQUEST['api_source']) && $_REQUEST['api_source']) ? true : false);
	
		$nocomment = 0;
		if (isset($_REQUEST['comments_enabled'])) {
			$nocomment = 1 - intval($_REQUEST['comments_enabled']);
		}

		// this is in days, convert to absolute time
		$channel_comments_closed = get_pconfig($profile_uid,'system','close_comments');
		if (intval($channel_comments_closed)) {
			$channel_comments_closed = datetime_convert(date_Default_timezone_get(),'UTC', 'now + ' . intval($channel_comments_closed) . ' days');
		}
		else {
			$channel_comments_closed = NULL_DATE;
		}

		$comments_closed = ((isset($_REQUEST['comments_closed']) && $_REQUEST['comments_closed']) ? datetime_convert(date_default_timezone_get(),'UTC',$_REQUEST['comments_closed']) : $channel_comments_closed);

		$is_poll = ((trim($_REQUEST['poll_answers'][0]) != '' && trim($_REQUEST['poll_answers'][1]) != '') ? true : false);

		// 'origin' (if non-zero) indicates that this network is where the message originated,
		// for the purpose of relaying comments to other conversation members. 
		// If using the API from a device (leaf node) you must set origin to 1 (default) or leave unset.
		// If the API is used from another network with its own distribution
		// and deliveries, you may wish to set origin to 0 or false and allow the other 
		// network to relay comments.
	
		// If you are unsure, it is prudent (and important) to leave it unset.   
	
		$origin = (($api_source && array_key_exists('origin',$_REQUEST)) ? intval($_REQUEST['origin']) : 1);
	
		// To represent message-ids on other networks - this will create an iconfig record
	
		$namespace = (($api_source && array_key_exists('namespace',$_REQUEST)) ? strip_tags($_REQUEST['namespace']) : '');
		$remote_id = (($api_source && array_key_exists('remote_id',$_REQUEST)) ? strip_tags($_REQUEST['remote_id']) : '');
	
		$owner_hash = null;
	
		$message_id  = ((x($_REQUEST,'message_id') && $api_source)  ? strip_tags($_REQUEST['message_id'])       : '');
		$created     = ((x($_REQUEST,'created'))     ? datetime_convert(date_default_timezone_get(),'UTC',$_REQUEST['created']) : datetime_convert());
		
		// Because somebody will probably try this and create a mess
		
		if ($created <= NULL_DATE) {
			$created = datetime_convert();
		}

		$post_id     = ((x($_REQUEST,'post_id'))     ? intval($_REQUEST['post_id'])        : 0);
		
		$app         = ((x($_REQUEST,'source'))      ? strip_tags($_REQUEST['source'])     : '');
		$return_path = ((x($_REQUEST,'return'))      ? $_REQUEST['return']                 : '');
		$preview     = ((x($_REQUEST,'preview'))     ? intval($_REQUEST['preview'])        : 0);
		$categories  = ((x($_REQUEST,'category'))    ? escape_tags($_REQUEST['category'])  : '');
		$webpage     = ((x($_REQUEST,'webpage'))     ? intval($_REQUEST['webpage'])        : 0);
		$item_obscured = ((x($_REQUEST,'obscured'))  ? intval($_REQUEST['obscured'])        : 0);
		$pagetitle   = ((x($_REQUEST,'pagetitle'))   ? escape_tags(urlencode($_REQUEST['pagetitle'])) : '');
		$layout_mid  = ((x($_REQUEST,'layout_mid'))  ? escape_tags($_REQUEST['layout_mid']): '');
		$plink       = ((x($_REQUEST,'permalink'))   ? escape_tags($_REQUEST['permalink']) : '');
		$obj_type    = ((x($_REQUEST,'obj_type'))    ? escape_tags($_REQUEST['obj_type'])  : ACTIVITY_OBJ_NOTE);


		$item_unpublished = ((isset($_REQUEST['draft']))    ? intval($_REQUEST['draft'])          : 0);

		// allow API to bulk load a bunch of imported items without sending out a bunch of posts. 
		$nopush      = ((x($_REQUEST,'nopush'))      ? intval($_REQUEST['nopush'])         : 0);
	
		/*
		 * Check service class limits
		 */
		if ($uid && !(x($_REQUEST,'parent')) && !(x($_REQUEST,'post_id'))) {
			$ret = $this->item_check_service_class($uid,(($_REQUEST['webpage'] == ITEM_TYPE_WEBPAGE) ? true : false));
			if (!$ret['success']) { 
				notice( t($ret['message']) . EOL) ;
				if($api_source)
					return ( [ 'success' => false, 'message' => 'service class exception' ] );	
				if(x($_REQUEST,'return')) 
					goaway(z_root() . "/" . $return_path );
				killme();
			}
		}
	
		if ($pagetitle) {
			$pagetitle = strtolower(URLify::transliterate($pagetitle));
		}
	
		$item_flags = $item_restrict = 0;
		$expires = NULL_DATE;
	
		$route = '';
		$parent_item = null;
		$parent_contact = null;
		$thr_parent = '';
		$parid = 0;
		$r = false;


		// If this is a comment, find the parent and preset some stuff

		if ($parent || $parent_mid) {
	
			if (! x($_REQUEST,'type')) {
				$_REQUEST['type'] = 'net-comment';
			}
			if ($obj_type == ACTIVITY_OBJ_NOTE) {
				$obj_type = ACTIVITY_OBJ_COMMENT;
			}

			// fetch the parent item
			
			if ($parent) {
				$r = q("SELECT * FROM item WHERE id = %d LIMIT 1",
					intval($parent)
				);
			}
			elseif ($parent_mid && $uid) {
				// This is coming from an API source, and we are logged in
				$r = q("SELECT * FROM item WHERE mid = '%s' AND uid = %d LIMIT 1",
					dbesc($parent_mid),
					intval($uid)
				);
			}
			// if this isn't the real parent of the conversation, find it
			if ($r) {
				$parid = $r[0]['parent'];
				$parent_mid = $r[0]['mid'];
				if ($r[0]['id'] != $r[0]['parent']) {
					$r = q("SELECT * FROM item WHERE id = parent AND parent = %d LIMIT 1",
						intval($parid)
					);
				}

				// if interacting with a pubstream item (owned by the sys channel), 
				// create a copy of the parent in your stream

				if ($r[0]['uid'] === $sys['channel_id'] && local_channel()) {
					$r = [ copy_of_pubitem(App::get_channel(), $r[0]['mid']) ];
				}
			}

			if (! $r) {
				notice( t('Unable to locate original post.') . EOL);
				if ($api_source) {
					return ( [ 'success' => false, 'message' => 'invalid post id' ] );
				}
				if (x($_REQUEST,'return')) { 
					goaway(z_root() . "/" . $return_path );
				}
				killme();
			}

			xchan_query($r,true);

			$parent_item = $r[0];
			$parent = $r[0]['id'];

			// multi-level threading - preserve the info but re-parent to our single level threading
	
			$thr_parent = $parent_mid;
	
			$route = $parent_item['route'];
	
		}

		$moderated = false;
	
		if (! $observer) {
			$observer = App::get_observer();
			if (! $observer) {
				// perhaps we're allowing moderated comments from anonymous viewers
				$observer = anon_identity_init($_REQUEST);
				if ($observer) {
					$moderated = true;
					$remote_xchan = $remote_observer = $observer;
				}
			}
		} 			
	
		if (! $observer) {
			notice( t('Permission denied.') . EOL) ;
			if ($api_source) {
				return ( [ 'success' => false, 'message' => 'permission denied' ] );
			}
			if (x($_REQUEST,'return')) {
				goaway(z_root() . "/" . $return_path );
			}
			killme();
		}

		if ($parent) {
			logger('mod_item: item_post parent=' . $parent);
			$can_comment = false;

			$can_comment = can_comment_on_post($observer['xchan_hash'],$parent_item);
			if (! $can_comment) {
				if ((array_key_exists('owner',$parent_item)) && intval($parent_item['owner']['abook_self']) == 1 ) {
					$can_comment = perm_is_allowed($profile_uid,$observer['xchan_hash'],'post_comments');
				}
			}
	
			if (! $can_comment) {
				notice( t('Permission denied.') . EOL) ;
				if($api_source)
					return ( [ 'success' => false, 'message' => 'permission denied' ] );	
				if(x($_REQUEST,'return')) 
					goaway(z_root() . "/" . $return_path );
				killme();
			}
		}
		else {
			// fixme - $webpage could also be a wiki page or article and require a different permission to be checked.	
			if(! perm_is_allowed($profile_uid,$observer['xchan_hash'],($webpage) ? 'write_pages' : 'post_wall')) {
				notice( t('Permission denied.') . EOL) ;
				if($api_source)
					return ( [ 'success' => false, 'message' => 'permission denied' ] );	
				if(x($_REQUEST,'return')) 
					goaway(z_root() . "/" . $return_path );
				killme();
			}
		}

		// check if this author is being moderated through the 'moderated' (negative) permission
		// when posting wall-to-wall
		if ($moderated === false && intval($uid) !== intval($profile_uid)) {
			$moderated = perm_is_allowed($profile_uid,$observer['xchan_hash'],'moderated');
		}

		// If this is a comment, check the moderated permission of the parent; who may be on another site	
		$remote_moderated = (($parent) ? their_perms_contains($profile_uid,$parent_item['owner_xchan'],'moderated') : false);
		if ($remote_moderated) {
			notice( t('Comment may be moderated.') . EOL);
		}

		// is this an edited post?
	
		$orig_post = null;
	
		if ($namespace && $remote_id) {
			// It wasn't an internally generated post - see if we've got an item matching this remote service id
			$i = q("select iid from iconfig where cat = 'system' and k = '%s' and v = '%s' limit 1",
				dbesc($namespace),
				dbesc($remote_id) 
			);
			if($i)
				$post_id = $i[0]['iid'];	
		}
	
		$iconfig = null;
	
		if($post_id) {
			$i = q("SELECT * FROM item WHERE uid = %d AND id = %d LIMIT 1",
				intval($profile_uid),
				intval($post_id)
			);
			if(! count($i))
				killme();
			$orig_post = $i[0];
			$iconfig = q("select * from iconfig where iid = %d",
				intval($post_id)
			);
		}
	
	
		if(! $channel) {
			if($uid && $uid == $profile_uid) {
				$channel = App::get_channel();
			}
			else {
				// posting as yourself but not necessarily to a channel you control
				$r = q("select * from channel left join account on channel_account_id = account_id where channel_id = %d LIMIT 1",
					intval($profile_uid)
				);
				if($r)
					$channel = $r[0];
			}
		}
	
	
		if(! $channel) {
			logger("mod_item: no channel.");
			if($api_source)
				return ( [ 'success' => false, 'message' => 'no channel' ] );	
			if(x($_REQUEST,'return')) 
				goaway(z_root() . "/" . $return_path );
			killme();
		}
	
		$owner_xchan = null;
	
		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($channel['channel_hash'])
		);
		if($r && count($r)) {
			$owner_xchan = $r[0];
		}
		else {
			logger("mod_item: no owner.");
			if($api_source)
				return ( [ 'success' => false, 'message' => 'no owner' ] );	
			if(x($_REQUEST,'return')) 
				goaway(z_root() . "/" . $return_path );
			killme();
		}
	
		$walltowall = false;
		$walltowall_comment = false;
	
		if($remote_xchan && ! $moderated)
			$observer = $remote_observer;
	
		if($observer) {
			logger('mod_item: post accepted from ' . $observer['xchan_name'] . ' for ' . $owner_xchan['xchan_name'], LOGGER_DEBUG);
	
			// wall-to-wall detection.
			// For top-level posts, if the author and owner are different it's a wall-to-wall
			// For comments, We need to additionally look at the parent and see if it's a wall post that originated locally.
	
			if($observer['xchan_name'] != $owner_xchan['xchan_name'])  {
				if(($parent_item) && ($parent_item['item_wall'] && $parent_item['item_origin'])) {
					$walltowall_comment = true;
					$walltowall = true;
				}
				if(! $parent) {
					$walltowall = true;		
				}
			}
		}
	
		$acl = new AccessControl($channel);

		$view_policy = PermissionLimits::Get($channel['channel_id'],'view_stream');	
		$comment_policy = ((isset($_REQUEST['comments_from']) && intval($_REQUEST['comments_from'])) ? intval($_REQUEST['comments_from']) : PermissionLimits::Get($channel['channel_id'],'post_comments'));

		$public_policy = ((x($_REQUEST,'public_policy')) ? escape_tags($_REQUEST['public_policy']) : map_scope($view_policy,true));
		if($webpage)
			$public_policy = '';
		if($public_policy)
			$private = 1;
	
		if($orig_post) {

			$private = 0;
			// webpages and unpublished drafts are allowed to change ACLs after the fact. Normal conversation items aren't. 
			if($webpage || intval($orig_post['item_unpublished'])) {
				$acl->set_from_array($_REQUEST);
			}
			else {
				$acl->set($orig_post);
				$public_policy     = $orig_post['public_policy'];
				$private           = $orig_post['item_private'];
			}
	
			if($public_policy || $acl->is_private()) {
				$private = (($private) ? $private : 1);
			}	
	
			$location          = $orig_post['location'];
			$coord             = $orig_post['coord'];
			$verb              = $orig_post['verb'];
			$app               = $orig_post['app'];
			$title             = escape_tags(trim($_REQUEST['title']));
			$summary           = trim($_REQUEST['summary']);
			$body              = trim($_REQUEST['body']);
	
			$item_flags        = $orig_post['item_flags'];
			$item_origin       = $orig_post['item_origin'];
			$item_unseen       = $orig_post['item_unseen'];
			$item_starred      = $orig_post['item_starred'];
			$item_uplink       = $orig_post['item_uplink'];
			$item_wall         = $orig_post['item_wall'];
			$item_thread_top   = $orig_post['item_thread_top'];
			$item_notshown     = $orig_post['item_notshown'];
			$item_nsfw         = $orig_post['item_nsfw'];
			$item_relay        = $orig_post['item_relay'];
			$item_mentionsme   = $orig_post['item_mentionsme'];
			$item_nocomment    = $orig_post['item_nocomment'];
			$item_obscured     = $orig_post['item_obscured'];
			$item_verified     = $orig_post['item_verified'];
			$item_retained     = $orig_post['item_retained'];
			$item_rss          = $orig_post['item_rss'];
			$item_deleted      = $orig_post['item_deleted'];
			$item_type         = $orig_post['item_type'];
			$item_hidden       = $orig_post['item_hidden'];
			$item_delayed      = $orig_post['item_delayed'];
			$item_pending_remove = $orig_post['item_pending_remove'];
			$item_blocked      = $orig_post['item_blocked'];
	
	
	
			$postopts          = $orig_post['postopts'];
			$created           = ((intval($orig_post['item_unpublished'])) ? $created : $orig_post['created']);
			$expires           = ((intval($orig_post['item_unpublished'])) ? NULL_DATE : $orig_post['expires']);
			$mid               = $orig_post['mid'];
			$parent_mid        = $orig_post['parent_mid'];
			$plink             = $orig_post['plink'];

		}
		else {
			if(! $walltowall) {
				if((array_key_exists('contact_allow',$_REQUEST))
					|| (array_key_exists('group_allow',$_REQUEST))
					|| (array_key_exists('contact_deny',$_REQUEST))
					|| (array_key_exists('group_deny',$_REQUEST))) {
					$acl->set_from_array($_REQUEST);
				}
				elseif(! $api_source) {
	
					// if no ACL has been defined and we aren't using the API, the form
					// didn't send us any parameters. This means there's no ACL or it has
					// been reset to the default audience.
					// If $api_source is set and there are no ACL parameters, we default
					// to the channel permissions which were set in the ACL contructor.
	
					$acl->set(array('allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => ''));
				}
			}
	
	
			$location          = ((isset($_REQUEST['location'])) ? notags(trim($_REQUEST['location'])) : EMPTY_STR);
			$coord             = ((isset($_REQUEST['coord'])) ? notags(trim($_REQUEST['coord'])) : EMPTY_STR);
			$verb              = ((isset($_REQUEST['verb'])) ? notags(trim($_REQUEST['verb'])) : EMPTY_STR);
			$title             = ((isset($_REQUEST['title'])) ? escape_tags(trim($_REQUEST['title'])) : EMPTY_STR);
			$summary           = ((isset($_REQUEST['summary'])) ? trim($_REQUEST['summary']) : EMPTY_STR);;
			$body              = ((isset($_REQUEST['body'])) ? trim($_REQUEST['body']) : EMPTY_STR);
			$body              .= ((isset($_REQUEST['attachment'])) ? trim($_REQUEST['attachment']) : EMPTY_STR);
			$postopts          = '';

			$allow_empty       = ((array_key_exists('allow_empty',$_REQUEST)) ? intval($_REQUEST['allow_empty']) : 0);	

			$private = ((isset($private) && $private) ? $private : intval($acl->is_private() || ($public_policy)));
	
			// If this is a comment, set the permissions from the parent.
	
			if($parent_item) {
				$private = 0;
				$acl->set($parent_item);
				$private = ((intval($parent_item['item_private']) ? $parent_item['item_private'] : $acl->is_private()));
				$public_policy     = $parent_item['public_policy'];
				$owner_hash        = $parent_item['owner_xchan'];
				$webpage           = $parent_item['item_type'];
				$comment_policy    = $parent_item['comment_policy'];
				$item_nocomment    = $parent_item['item_nocomment'];
				$comments_closed   = $parent_item['comments_closed'];
			}
		
			if((! $allow_empty) && (! strlen($body))) {
				if($preview)
					killme();
				info( t('Empty post discarded.') . EOL );
				if($api_source)
					return ( [ 'success' => false, 'message' => 'no content' ] );	
				if(x($_REQUEST,'return')) 
					goaway(z_root() . "/" . $return_path );
				killme();
			}
		}
		
	
	
		if(Apps::system_app_installed($profile_uid,'Expire Posts')) {
			if(x($_REQUEST,'expire')) {
				$expires = datetime_convert(date_default_timezone_get(),'UTC', $_REQUEST['expire']);
				if($expires <= datetime_convert())
					$expires = NULL_DATE;
			}
		}


		$mimetype = notags(trim($_REQUEST['mimetype']));
		if(! $mimetype)
			$mimetype = 'text/bbcode';
	

		$execflag = ((intval($uid) == intval($profile_uid) 
			&& ($channel['channel_pageflags'] & PAGE_ALLOWCODE)) ? true : false);

		if($preview) {
			$summary = z_input_filter($summary,$mimetype,$execflag);
			$body = z_input_filter($body,$mimetype,$execflag);
		}


		$arr = [ 'profile_uid' => $profile_uid, 'summary' => $summary, 'content' => $body, 'mimetype' => $mimetype ];
		call_hooks('post_content',$arr);
		$summary = $arr['summary'];
		$body = $arr['content'];
		$mimetype = $arr['mimetype'];

	
		$gacl = $acl->get();
		$str_contact_allow = $gacl['allow_cid'];
		$str_group_allow   = $gacl['allow_gid'];
		$str_contact_deny  = $gacl['deny_cid'];
		$str_group_deny    = $gacl['deny_gid'];


		// if the acl contains a single contact and it's a group, add a mention. This is for compatibility
		// with other groups implementations which require a mention to trigger group delivery. 

		if (($str_contact_allow) && (! $str_group_allow) && (! $str_contact_deny) && (! $str_group_deny)) {
			$cida = expand_acl($str_contact_allow);
			if (count($cida) === 1) {
				$netgroups = get_forum_channels($profile_uid,1);
				if ($netgroups)  {
					foreach($netgroups as $ng) {
						if ($ng['xchan_hash'] == $cida[0]) {
							if (! is_array($post_tags)) {
								$post_tags = [];
							}
							$post_tags[] = array(
								'uid'   => $profile_uid, 
								'ttype' => TERM_MENTION,
								'otype' => TERM_OBJ_POST,
								'term'  => $ng['xchan_name'],
								'url'   => $ng['xchan_url']
							);
						}
					}
				}
			}
		}

		$groupww = false;

		// if this is a wall-to-wall post to a group, turn it into a direct message
		
		$role = get_pconfig($profile_uid,'system','permissions_role');

		$rolesettings = PermissionRoles::role_perms($role);

		$channel_type = isset($rolesettings['channel_type']) ? $rolesettings['channel_type'] : 'normal';

		$is_group = (($channel_type === 'group') ? true : false);

		if (($is_group) && ($walltowall) && (! $walltowall_comment)) {				
			$groupww = true;
			$str_contact_allow = $owner_xchan['xchan_hash'];
			$str_group_allow = '';
		}


		if($mimetype === 'text/bbcode') {
		
			// BBCODE alert: the following functions assume bbcode input
			// and will require alternatives for alternative content-types (text/html, text/markdown, text/plain, etc.)
			// we may need virtual or template classes to implement the possible alternatives

			if(strpos($body,'[/summary]') !== false) {
				$match = '';
				$cnt = preg_match("/\[summary\](.*?)\[\/summary\]/ism",$body,$match);
				if($cnt) {
					$summary .= $match[1];
				}
				$body_content = preg_replace("/^(.*?)\[summary\](.*?)\[\/summary\]/ism", '',$body);
				$body = trim($body_content);
			}

			$summary = cleanup_bbcode($summary);
			$body = cleanup_bbcode($body);
	
			// Look for tags and linkify them
			$summary_tags = linkify_tags($summary, ($uid) ? $uid : $profile_uid);
			$body_tags = linkify_tags($body, ($uid) ? $uid : $profile_uid);
			$comment_tags = linkify_tags($hidden_mentions, ($uid) ? $uid : $profile_uid);
			
			foreach ( [ $summary_tags, $body_tags, $comment_tags ] as $results ) {

				if ($results) {
	
					// Set permissions based on tag replacements
					set_linkified_perms($results, $str_contact_allow, $str_group_allow, $profile_uid, $parent_item, $private);
	
					if (! isset($post_tags)) {
						$post_tags = [];
					}
					foreach ($results as $result) {
						$success = $result['success'];
						if ($success['replaced']) {
						
							// suppress duplicate mentions/tags
							$already_tagged = false;
							foreach ($post_tags as $pt) {
								if ($pt['term'] === $success['term'] && $pt['url'] === $success['url'] && intval($pt['ttype']) === intval($success['termtype'])) {
									$already_tagged = true;
									break;
								}
							}
							if ($already_tagged) {
								continue;
							}

							$post_tags[] = array(
								'uid'   => $profile_uid, 
								'ttype' => $success['termtype'],
								'otype' => TERM_OBJ_POST,
								'term'  => $success['term'],
								'url'   => $success['url']
							);

							// support #collection syntax to post to a collection
							// this is accomplished by adding a pcategory tag for each collection target
							// this is checked inside tag_deliver() to create a second delivery chain

							if ($success['termtype'] === TERM_HASHTAG) {
								$r = q("select xchan_url from channel left join xchan on xchan_hash = channel_hash where channel_address = '%s' and channel_parent = '%s' and channel_removed = 0",
									dbesc($success['term']),
									dbesc(get_observer_hash())
								);
								if ($r) {
									$post_tags[] = [
										'uid'   => $profile_uid, 
										'ttype' => TERM_PCATEGORY,
										'otype' => TERM_OBJ_POST,
										'term'  => $success['term'] . '@' . App::get_hostname(),
										'url'   => $r[0]['xchan_url']
									];
								}
							}
						}
					}
				}
			}


			/**
			 * process collections selected manually 
			 */
		 
			if (array_key_exists('collections',$_REQUEST) && is_array($_REQUEST['collections']) && count($_REQUEST['collections'])) {
				foreach ($_REQUEST['collections'] as $clct) {
					$r = q("select xchan_url, xchan_hash from xchan left join hubloc on hubloc_hash = xchan_hash where hubloc_addr = '%s' limit 1",
						dbesc($clct)
					);
					if ($r) { 
						if (! isset($post_tags)) {
							$post_tags = [];
						}
						$post_tags[] = [
							'uid'   => $profile_uid, 
							'ttype' => TERM_PCATEGORY,
							'otype' => TERM_OBJ_POST,
							'term'  => $clct,
							'url'   => $r[0]['xchan_url']
						];
					}
				}
			}

			if(($str_contact_allow) && (! $str_group_allow)) {
				// direct message - private between individual channels but not groups
				$private = 2;
			}

			if ($private) {
			
				// for edited posts, re-use any existing OCAP token (if found).
				// Otherwise generate a new one.
				
				if ($iconfig) {
					foreach ($iconfig as $cfg) {
						if ($cfg['cat'] === 'ocap' && $cfg['k'] === 'relay') {
							$token = $cfg['v'];
						}
					}
				}
				if (! $token) {
					$token = new_token();
				}
			}

	
			/**
			 *
			 * When a photo was uploaded into the message using the (profile wall) ajax 
			 * uploader, The permissions are initially set to disallow anybody but the
			 * owner from seeing it. This is because the permissions may not yet have been
			 * set for the post. If it's private, the photo permissions should be set
			 * appropriately. But we didn't know the final permissions on the post until
			 * now. So now we'll look for links of uploaded photos and attachments that are in the
			 * post and set them to the same permissions as the post itself.
			 *
			 * If the post was end-to-end encrypted we can't find images and attachments in the body,
			 * use our media_str input instead which only contains these elements - but only do this
			 * when encrypted content exists because the photo/attachment may have been removed from 
			 * the post and we should keep it private. If it's encrypted we have no way of knowing
			 * so we'll set the permissions regardless and realise that the media may not be 
			 * referenced in the post. 
			 *
			 */
	
			if(! $preview) {
				fix_attached_permissions($profile_uid,((strpos($body,'[/crypt]')) ? $_POST['media_str'] : $body),$str_contact_allow,$str_group_allow,$str_contact_deny,$str_group_deny, $token);	
			}
	
	
			$attachments = '';
			$match = false;
	
			if(preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/',$body,$match)) {
				$attachments = [];
				$i = 0;
				foreach($match[2] as $mtch) {
					$attach_link = '';
					$hash = substr($mtch,0,strpos($mtch,','));
					$rev = intval(substr($mtch,strpos($mtch,',')));
					$r = attach_by_hash_nodata($hash, $observer['xchan_hash'], $rev);
					if($r['success']) {
						$attachments[] = array(
							'href'     => z_root() . '/attach/' . $r['data']['hash'],
							'length'   => $r['data']['filesize'],
							'type'     => $r['data']['filetype'],
							'title'    => urlencode($r['data']['filename']),
							'revision' => $r['data']['revision']
						);
					}
					$body = str_replace($match[1][$i],$attach_link,$body);
					$i++;
				}
			}


			if(preg_match_all('/(\[share=(.*?)\](.*?)\[\/share\])/',$body,$match)) {
				// process share by id				

				$i = 0;
				foreach($match[2] as $mtch) {
					$reshare = new \Zotlabs\Lib\Share($mtch);
					$body = str_replace($match[1][$i],$reshare->bbcode(),$body);
					$i++;
				}
			}
	
		}
	
	// BBCODE end alert
	
		if(strlen($categories)) {
			if (! isset($post_tags)) {
				$post_tags = [];
			}
	
			$cats = explode(',',$categories);
			foreach($cats as $cat) {

				if($webpage == ITEM_TYPE_CARD) {
					$catlink = z_root() . '/cards/' . $channel['channel_address'] . '?f=&cat=' . urlencode(trim($cat));
				}
				elseif($webpage == ITEM_TYPE_ARTICLE) {
					$catlink = z_root() . '/articles/' . $channel['channel_address'] . '?f=&cat=' . urlencode(trim($cat));
				}
				else {
					$catlink = $owner_xchan['xchan_url'] . '?f=&cat=' . urlencode(trim($cat));
				}

				$post_tags[] = array(
					'uid'   => $profile_uid, 
					'ttype' => TERM_CATEGORY,
					'otype' => TERM_OBJ_POST,
					'term'  => trim($cat),
					'url'   => $catlink
				); 				
			}
		}
	
		if($orig_post) {
			// preserve original tags
			$t = q("select * from term where oid = %d and otype = %d and uid = %d and ttype in ( %d, %d, %d )",
				intval($orig_post['id']),
				intval(TERM_OBJ_POST),
				intval($profile_uid),
				intval(TERM_UNKNOWN),
				intval(TERM_FILE),
				intval(TERM_COMMUNITYTAG)
			);
			if($t) {
				if (! isset($post_tags)) {
					$post_tags = [];
				}

				foreach($t as $t1) {
					$post_tags[] = array(
						'uid'   => $profile_uid, 
						'ttype' => $t1['ttype'],
						'otype' => TERM_OBJ_POST,
						'term'  => $t1['term'],
						'url'   => $t1['url'],
					); 				
				}
			}
		} 
	
	
		$item_unseen = ((local_channel() != $profile_uid) ? 1 : 0);
		$item_wall = ((isset($_REQUEST['type']) && ($_REQUEST['type'] === 'wall' || $_REQUEST['type'] === 'wall-comment')) ? 1 : 0);
		$item_origin = (($origin) ? 1 : 0);
		$item_nocomment = ((isset($item_nocomment)) ? $item_nocomment : $nocomment);
	
	
		// determine if this is a wall post
	
		if($parent) {
			$item_wall = $parent_item['item_wall'];
		}
		else {
			if(! $webpage) {
				$item_wall = 1;
			}
		}
	
	
		if($moderated)
			$item_blocked = ITEM_MODERATED;
	
			
		if(! strlen($verb))
			$verb = ACTIVITY_POST ;
	
		$notify_type = (($parent) ? 'comment-new' : 'wall-new' );
	
		if (! (isset($mid) && $mid)) {
			if($message_id) {
				$mid = $message_id;
			}
			else {
				$uuid = new_uuid();
				$mid = z_root() . '/item/' . $uuid;
			}
		}


		if($is_poll) {
			$poll = [
				'question' => $body,
				'answers' => $_REQUEST['poll_answers'],
				'multiple_answers' => $_REQUEST['poll_multiple_answers'],
				'expire_value' => $_REQUEST['poll_expire_value'],
				'expire_unit' => $_REQUEST['poll_expire_unit']
			];
			$obj = $this->extract_poll_data($poll, [ 'item_private' => $private, 'allow_cid' => $str_contact_allow, 'allow_gid' => $str_contact_deny ]);
		}
		else {
			$obj = $this->extract_bb_poll_data($body,[ 'item_private' => $private, 'allow_cid' => $str_contact_allow, 'allow_gid' => $str_contact_deny ]);
		}


		if ($obj) {
			$obj['url'] = $mid;
			$obj['attributedTo'] = channel_url($channel);
			$datarray['obj'] = $obj;
			$obj_type = 'Question';
			if ($obj['endTime']) {
				$d = datetime_convert('UTC','UTC', $obj['endTime']);
				if ($d > NULL_DATE) {
					$comments_closed = $d;	
				}
			}
		}

		if(! $parent_mid) {
			$parent_mid = $mid;
		}
	
		if($parent_item)
			$parent_mid = $parent_item['mid'];



		// Fallback so that we alway have a thr_parent
	
		if(!$thr_parent)
			$thr_parent = $mid;
	

		$item_thread_top = ((! $parent) ? 1 : 0);
	

		// fix permalinks for cards, etc.
	
		if($webpage == ITEM_TYPE_CARD) {
			$plink = z_root() . '/cards/' . $channel['channel_address'] . '/' . (($pagetitle) ? $pagetitle : $uuid);
		}
		if(($parent_item) && ($parent_item['item_type'] == ITEM_TYPE_CARD)) {
			$r = q("select v from iconfig where iconfig.cat = 'system' and iconfig.k = 'CARD' and iconfig.iid = %d limit 1",
				intval($parent_item['id'])
			);
			if($r) {
				$plink = z_root() . '/cards/' . $channel['channel_address'] . '/' . $r[0]['v'];
			}
		}

		if($webpage == ITEM_TYPE_ARTICLE) {
			$plink = z_root() . '/articles/' . $channel['channel_address'] . '/' . (($pagetitle) ? $pagetitle : $uuid);
		}
		if(($parent_item) && ($parent_item['item_type'] == ITEM_TYPE_ARTICLE)) {
			$r = q("select v from iconfig where iconfig.cat = 'system' and iconfig.k = 'ARTICLE' and iconfig.iid = %d limit 1",
				intval($parent_item['id'])
			);
			if($r) {
				$plink = z_root() . '/articles/' . $channel['channel_address'] . '/' . $r[0]['v'];
			}
		}

		if ((! (isset($plink) && $plink)) && $item_thread_top) {
			$plink = z_root() . '/item/' . $uuid;
		}

		if (array_path_exists('obj/id',$datarray)) {
			$datarray['obj']['id'] = $mid;
		}

		$datarray['aid']                 = $channel['channel_account_id'];
		$datarray['uid']                 = $profile_uid;
		$datarray['uuid']                = $uuid;
		$datarray['owner_xchan']         = (($owner_hash) ? $owner_hash : $owner_xchan['xchan_hash']);
		$datarray['author_xchan']        = $observer['xchan_hash'];
		$datarray['created']             = $created;
		$datarray['edited']              = (($orig_post && (! intval($orig_post['item_unpublished']))) ? datetime_convert() : $created);
		$datarray['expires']             = $expires;
		$datarray['commented']           = (($orig_post && (! intval($orig_post['item_unpublished']))) ? datetime_convert() : $created);
		$datarray['received']            = (($orig_post && (! intval($orig_post['item_unpublished']))) ? datetime_convert() : $created);
		$datarray['changed']             = (($orig_post && (! intval($orig_post['item_unpublished']))) ? datetime_convert() : $created);
		$datarray['comments_closed']     = $comments_closed;
		$datarray['mid']                 = $mid;
		$datarray['parent_mid']          = $parent_mid;
		$datarray['mimetype']            = $mimetype;
		$datarray['title']               = $title;
		$datarray['summary']             = $summary;
		$datarray['body']                = $body;
		$datarray['app']                 = $app;
		$datarray['location']            = $location;
		$datarray['coord']               = $coord;
		$datarray['verb']                = $verb;
		$datarray['obj_type']            = $obj_type;
		$datarray['allow_cid']           = $str_contact_allow;
		$datarray['allow_gid']           = $str_group_allow;
		$datarray['deny_cid']            = $str_contact_deny;
		$datarray['deny_gid']            = $str_group_deny;
		$datarray['attach']              = $attachments;
		$datarray['thr_parent']          = $thr_parent;
		$datarray['postopts']            = $postopts;
		$datarray['item_unseen']         = intval($item_unseen);
		$datarray['item_wall']           = intval($item_wall);
		$datarray['item_origin']         = intval($item_origin);
		$datarray['item_type']           = $webpage;
		$datarray['item_private']        = intval($private);
		$datarray['item_thread_top']     = intval($item_thread_top);
		$datarray['item_unseen']         = intval($item_unseen);
		$datarray['item_starred']        = intval($item_starred);
		$datarray['item_uplink']         = intval($item_uplink);
		$datarray['item_consensus']      = 0;
		$datarray['item_notshown']       = intval($item_notshown);
		$datarray['item_nsfw']           = intval($item_nsfw);
		$datarray['item_relay']          = intval($item_relay);
		$datarray['item_mentionsme']     = intval($item_mentionsme);
		$datarray['item_nocomment']      = intval($item_nocomment);
		$datarray['item_obscured']       = intval($item_obscured);
		$datarray['item_verified']       = intval($item_verified);
		$datarray['item_retained']       = intval($item_retained);
		$datarray['item_rss']            = intval($item_rss);
		$datarray['item_deleted']        = intval($item_deleted);
		$datarray['item_hidden']         = intval($item_hidden);
		$datarray['item_unpublished']    = intval($item_unpublished);
		$datarray['item_delayed']        = intval($item_delayed);
		$datarray['item_pending_remove'] = intval($item_pending_remove);
		$datarray['item_blocked']        = intval($item_blocked);	
		$datarray['layout_mid']          = $layout_mid;
		$datarray['public_policy']       = $public_policy;
		$datarray['comment_policy']      = ((is_numeric($comment_policy)) ? map_scope($comment_policy) : $comment_policy); // only map scope if it is numeric, otherwise use what we have
		$datarray['term']                = $post_tags;
		$datarray['plink']               = $plink;
		$datarray['route']               = $route;


		// A specific ACL over-rides public_policy completely
 
		if(! empty_acl($datarray))
			$datarray['public_policy'] = '';

		if ($iconfig) {
			$datarray['iconfig'] = $iconfig;
		}
		if ($private) {
			IConfig::set($datarray,'ocap','relay',$token);
		}

		if(! array_key_exists('obj',$datarray)) {
			$copy = $datarray;
			$copy['author'] = $observer;
			$datarray['obj'] = Activity::encode_item($copy,((get_config('system','activitypub', ACTIVITYPUB_ENABLED)) ? true : false));
		}	

		Activity::rewrite_mentions($datarray);
		
		// preview mode - prepare the body for display and send it via json
	
		if($preview) {
			require_once('include/conversation.php');
	
			$datarray['owner'] = $owner_xchan;
			$datarray['author'] = $observer;
			$datarray['attach'] = json_encode($datarray['attach']);
			$o = conversation(array($datarray),'search',false,'preview');
	//		logger('preview: ' . $o, LOGGER_DEBUG);
			echo json_encode(array('preview' => $o));
			killme();
		}
		if($orig_post)
			$datarray['edit'] = true;
	
		// suppress duplicates, *unless* you're editing an existing post. This could get picked up
		// as a duplicate if you're editing it very soon after posting it initially and you edited
		// some attribute besides the content, such as title or categories. 

		if(feature_enabled($profile_uid,'suppress_duplicates') && (! $orig_post)) {
	
			$z = q("select created from item where uid = %d and created > %s - INTERVAL %s and body = '%s' limit 1",
				intval($profile_uid),
				db_utcnow(),
				db_quoteinterval('2 MINUTE'),
				dbesc($body)
			);
	
			if($z) {
				$datarray['cancel'] = 1;
				notice( t('Duplicate post suppressed.') . EOL);
				logger('Duplicate post. Cancelled.');
			}
		}
	
		call_hooks('post_local',$datarray);
	
		if(x($datarray,'cancel')) {
			logger('mod_item: post cancelled by plugin or duplicate suppressed.');
			if($return_path)
				goaway(z_root() . "/" . $return_path);
			if($api_source)
				return ( [ 'success' => false, 'message' => 'operation cancelled' ] );	
			$json = array('cancel' => 1);
			$json['reload'] = z_root() . '/' . $_REQUEST['jsreload'];
			echo json_encode($json);
			killme();
		}
	
	
		if(mb_strlen($datarray['title']) > 191)
			$datarray['title'] = mb_substr($datarray['title'],0,191);
	
		if($webpage) {
			IConfig::Set($datarray,'system', webpage_to_namespace($webpage),
				(($pagetitle) ? $pagetitle : basename($datarray['mid'])), true);
		}
		elseif($namespace) {
			IConfig::Set($datarray,'system', $namespace,
				(($remote_id) ? $remote_id : basename($datarray['mid'])), true);
		}

		if (intval($datarray['item_unpublished'])) {
			$draft_msg = t('Draft saved. Use <a href="stream?draft=1">Drafts</a> app to continue editing.');
		}

		if($orig_post) {
			$datarray['id'] = $post_id;
	
			$x = item_store_update($datarray,$execflag);
			if(! $parent) {
				$r = q("select * from item where id = %d",
					intval($post_id)
				);
				if($r) {
					xchan_query($r);
					$sync_item = fetch_post_tags($r);
					Libsync::build_sync_packet($profile_uid,array('item' => array(encode_item($sync_item[0],true))));
				}
			}
			if(! $nopush)
				Run::Summon( [ 'Notifier', 'edit_post', $post_id ] );
	

			if($api_source)
				return($x);


			if (intval($datarray['item_unpublished'])) {
				info($draft_msg);
			}



			if((x($_REQUEST,'return')) && strlen($return_path)) {
				logger('return: ' . $return_path);
				goaway(z_root() . "/" . $return_path );
			}
			killme();
		}
		else
			$post_id = 0;
	
		$post = item_store($datarray,$execflag);
	
		$post_id = $post['item_id'];
		$datarray = $post['item'];

		if($post_id) {
			logger('mod_item: saved item ' . $post_id);
	
			if($parent) {
	
				// prevent conversations which you are involved from being expired

				if(local_channel())
					retain_item($parent);

				// only send comment notification if this is a wall-to-wall comment and not a DM,
				// otherwise it will happen during delivery
	
				if(($datarray['owner_xchan'] != $datarray['author_xchan']) && (intval($parent_item['item_wall'])) && intval($datarray['item_private']) != 2) {
					Enotify::submit(array(
						'type'         => NOTIFY_COMMENT,
						'from_xchan'   => $datarray['author_xchan'],
						'to_xchan'     => $datarray['owner_xchan'],
						'item'         => $datarray,
						'link'		   => z_root() . '/display/' . gen_link_id($datarray['mid']),
						'verb'         => ACTIVITY_POST,
						'otype'        => 'item',
						'parent'       => $parent,
						'parent_mid'   => $parent_item['mid']
					));
				
				}
			}
			else {
				$parent = $post_id;
	
				if(($datarray['owner_xchan'] != $datarray['author_xchan']) && ($datarray['item_type'] == ITEM_TYPE_POST)) {
					Enotify::submit(array(
						'type'         => NOTIFY_WALL,
						'from_xchan'   => $datarray['author_xchan'],
						'to_xchan'     => $datarray['owner_xchan'],
						'item'         => $datarray,
						'link'		   => z_root() . '/display/' . gen_link_id($datarray['mid']),
						'verb'         => ACTIVITY_POST,
						'otype'        => 'item'
					));
				}
	
				if($uid && $uid == $profile_uid && (is_item_normal($datarray))) {
					q("update channel set channel_lastpost = '%s' where channel_id = %d",
						dbesc(datetime_convert()),
						intval($uid)
					);
				}
			}
	
			// photo comments turn the corresponding item visible to the profile wall
			// This way we don't see every picture in your new photo album posted to your wall at once.
			// They will show up as people comment on them.
	
			if(intval($parent_item['item_hidden'])) {
				$r = q("UPDATE item SET item_hidden = 0 WHERE id = %d",
					intval($parent_item['id'])
				);
			}
		}
		else {
			logger('mod_item: unable to retrieve post that was just stored.');
			notice( t('System error. Post not saved.') . EOL);
			if($return_path)
				goaway(z_root() . "/" . $return_path );
			if($api_source)
				return ( [ 'success' => false, 'message' => 'system error' ] );
			killme();
		}
		
		if(($parent) && ($parent != $post_id)) {
			// Store the comment signature information in case we need to relay to Diaspora
			//$ditem = $datarray;
			//$ditem['author'] = $observer;
			//store_diaspora_comment_sig($ditem,$channel,$parent_item, $post_id, (($walltowall_comment) ? 1 : 0));
		}
		else {
			$r = q("select * from item where id = %d",
				intval($post_id)
			);
			if($r) {
				xchan_query($r);
				$sync_item = fetch_post_tags($r);
				Libsync::build_sync_packet($profile_uid,array('item' => array(encode_item($sync_item[0],true))));
			}
		}
	
		$datarray['id']    = $post_id;
		$datarray['llink'] = z_root() . '/display/' . gen_link_id($datarray['mid']);
	
		call_hooks('post_local_end', $datarray);

		if ($groupww) {
			$nopush = false;
		}

		if(! $nopush) {
			Run::Summon( [ 'Notifier', $notify_type, $post_id ] );
		}
		logger('post_complete');

		if($moderated) {
			info(t('Your post/comment is awaiting approval.') . EOL);
		}
	
		// figure out how to return, depending on from whence we came
	
		if($api_source)
			return $post;

		if (intval($datarray['item_unpublished'])) {
			info($draft_msg);
		}

		if($return_path) {
			goaway(z_root() . "/" . $return_path);
		}
	
		$json = array('success' => 1);
		if(x($_REQUEST,'jsreload') && strlen($_REQUEST['jsreload']))
			$json['reload'] = z_root() . '/' . $_REQUEST['jsreload'];
	
		logger('post_json: ' . print_r($json,true), LOGGER_DEBUG);
	
		echo json_encode($json);
		killme();
		// NOTREACHED
	}
	
	
	function get() {
	
		if((! local_channel()) && (! remote_channel()))
			return;

		// allow pinned items to be dropped. 'pin-' was prepended to the id of these
		// items so that they would have a unique html id even if the pinned item
		// was also displayed in a normal conversation on the same web page.
		
		$drop_id = str_replace('pin-','',argv(2));

		if((argc() == 3) && (argv(1) === 'drop') && intval($drop_id)) {
	
			$i = q("select * from item where id = %d limit 1",
				intval($drop_id)
			);
	
			if($i) {
				$can_delete = false;
				$local_delete = false;
				$regular_delete = false;

				if(local_channel() && local_channel() == $i[0]['uid']) {
					$local_delete = true;
				}
	
				$ob_hash = get_observer_hash();
				if($ob_hash && ($ob_hash === $i[0]['author_xchan'] || $ob_hash === $i[0]['owner_xchan'] || $ob_hash === $i[0]['source_xchan'])) {
					$can_delete = true;
					$regular_delete = true;
				}

				// The site admin can delete any post/item on the site.
				// If the item originated on this site+channel the deletion will propagate downstream. 
				// Otherwise just the local copy is removed.

				if(is_site_admin()) {
					$local_delete = true;
					if(intval($i[0]['item_origin']))
						$can_delete = true;
				}


				if(! ($can_delete || $local_delete)) {
					notice( t('Permission denied.') . EOL);
					return;
				}

				if ($i[0]['resource_type'] === 'event') {
					// delete and sync the event separately
					$r = q("SELECT * FROM event WHERE event_hash = '%s' AND uid = %d LIMIT 1",
						dbesc($i[0]['resource_id']),
						intval($i[0]['uid'])
					);
					if ($r && $regular_delete) {
						$sync_event = $r[0];
						q("delete from event WHERE event_hash = '%s' AND uid = %d LIMIT 1",
							dbesc($i[0]['resource_id']),
							intval($i[0]['uid'])
						);
						$sync_event['event_deleted'] = 1;
						Libsync::build_sync_packet($i[0]['uid'],array('event' => array($sync_event)));
					}
				}

				if ($i[0]['resource_type'] === 'photo') {
					attach_delete($i[0]['uid'], $i[0]['resource_id'], true );
					$ch = channelx_by_n($i[0]['uid']);
					if ($ch && $regular_delete) {
						$sync = attach_export_data($ch,$i[0]['resource_id'], true);
						if ($sync) {
							Libsync::build_sync_packet($i[0]['uid'],array('file' => array($sync)));
						}
					}
				}


				// if this is a different page type or it's just a local delete
				// but not by the item author or owner, do a simple deletion

				$complex = false;	

				if(intval($i[0]['item_type']) || ($local_delete && (! $can_delete))) {
					drop_item($i[0]['id']);
				}
				else {
					// complex deletion that needs to propagate and be performed in phases
					drop_item($i[0]['id'],true,DROPITEM_PHASE1);
					$complex = true;
				}

				$r = q("select * from item where id = %d",
					intval($i[0]['id'])
				);
				if($r) {
					xchan_query($r);
					$sync_item = fetch_post_tags($r);
					Libsync::build_sync_packet($i[0]['uid'],array('item' => array(encode_item($sync_item[0],true))));
				}

				if($complex) {
					tag_deliver($i[0]['uid'],$i[0]['id']);
				}
			}
		}
	}
	
	
	
	function item_check_service_class($channel_id,$iswebpage) {
		$ret = array('success' => false, 'message' => '');
	
		if ($iswebpage) {
			$r = q("select count(i.id)  as total from item i 
				right join channel c on (i.author_xchan=c.channel_hash and i.uid=c.channel_id )  
				and i.parent=i.id and i.item_type = %d and i.item_deleted = 0 and i.uid= %d ",
				intval(ITEM_TYPE_WEBPAGE),
				intval($channel_id)
			);
		}
		else {
			$r = q("select count(id) as total from item where parent = id and item_wall = 1 and uid = %d " . item_normal(),
				intval($channel_id)
			);
		}
	
		if(! $r) {
			$ret['message'] = t('Unable to obtain post information from database.');
			return $ret;
		} 


		if (!$iswebpage) {
			$max = engr_units_to_bytes(service_class_fetch($channel_id,'total_items'));
			if(! service_class_allows($channel_id,'total_items',$r[0]['total'])) {
				$result['message'] .= upgrade_message() . sprintf( t('You have reached your limit of %1$.0f top level posts.'),$max);
				return $result;
			}
		}
		else {
			$max = engr_units_to_bytes(service_class_fetch($channel_id,'total_pages'));
			if(! service_class_allows($channel_id,'total_pages',$r[0]['total'])) {
				$result['message'] .= upgrade_message() . sprintf( t('You have reached your limit of %1$.0f webpages.'),$max);
				return $result;
			}	
		}
	
		$ret['success'] = true;
		return $ret;
	}
	
	function extract_bb_poll_data(&$body,$item) {

		$multiple = false;

		if (strpos($body,'[/question]') === false && strpos($body,'[/answer]') === false) {
			return false;
		}
		if (strpos($body,'[nobb]') !== false) {
			return false;
		}

		$obj = [];
		$ptr = [];
		$matches = null;
		$obj['type'] = 'Question';

		if (preg_match_all('/\[answer\](.*?)\[\/answer\]/ism',$body,$matches,PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$ptr[] = [ 'name' => $match[1], 'type' => 'Note', 'replies' => [ 'type' => 'Collection', 'totalItems' => 0 ]];
				$body = str_replace('[answer]' . $match[1] . '[/answer]', EMPTY_STR, $body);
			}
		}

		$matches = null;

		if (preg_match('/\[question\](.*?)\[\/question\]/ism',$body,$matches)) {
			$obj['content'] = bbcode($matches[1]);
			$body = str_replace('[question]' . $matches[1] . '[/question]', $matches[1], $body);
			$obj['oneOf'] = $ptr;
		}

		$matches = null;
		
		if (preg_match('/\[question=multiple\](.*?)\[\/question\]/ism',$body,$matches)) {
			$obj['content'] = bbcode($matches[1]);
			$body = str_replace('[question=multiple]' . $matches[1] . '[/question]', $matches[1], $body);
			$obj['anyOf'] = $ptr;
		}

		$matches = null;
		
		if (preg_match('/\[ends\](.*?)\[\/ends\]/ism',$body,$matches)) {
			$obj['endTime'] = datetime_convert(date_default_timezone_get(),'UTC', $matches[1],ATOM_TIME);
			$body = str_replace('[ends]' . $matches[1] . '[/ends]', EMPTY_STR, $body);
		}

		if ($item['item_private']) {
			$obj['to'] = Activity::map_acl($item);
		}
		else {
			$obj['to'] = [ ACTIVITY_PUBLIC_INBOX ];
		}
		
		return $obj;

	}

	function extract_poll_data($poll, $item) {

		$multiple = intval($poll['multiple_answers']);
		$expire_value = intval($poll['expire_value']);
		$expire_unit = $poll['expire_unit'];
		$question = $poll['question'];
		$answers = $poll['answers'];

		$obj = [];
		$ptr = [];
		$obj['type'] = 'Question';
		$obj['content'] = bbcode($question);

		foreach($answers as $answer) {
			if(trim($answer))
				$ptr[] = [ 'name' => escape_tags($answer), 'type' => 'Note', 'replies' => [ 'type' => 'Collection', 'totalItems' => 0 ]];
		}

		if($multiple) {
			$obj['anyOf'] = $ptr;
		}
		else {
			$obj['oneOf'] = $ptr;
		}

		$obj['endTime'] = datetime_convert(date_default_timezone_get(), 'UTC', 'now + ' . $expire_value . ' ' . $expire_unit, ATOM_TIME);

		if ($item['item_private']) {
			$obj['to'] = Activity::map_acl($item);
		}
		else {
			$obj['to'] = [ ACTIVITY_PUBLIC_INBOX ];
		}

		return $obj;

	}

}
