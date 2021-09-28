<?php
namespace Zotlabs\Module;

// ActivityPub delivery endpoint


use App;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\PConfig;

class Inbox extends Controller {

	function post() {

		// This SHOULD be handled by the webserver, but in the RFC it is only indicated as
		// a SHOULD and not a MUST, so some webservers fail to reject appropriately.

		if ((array_key_exists('HTTP_ACCEPT',$_SERVER)) && ($_SERVER['HTTP_ACCEPT']) 
			&& (strpos($_SERVER['HTTP_ACCEPT'],'*') === false) && (! ActivityStreams::is_as_request())) {
			logger('unhandled accept header: ' . $_SERVER['HTTP_ACCEPT'],LOGGER_DEBUG);
			http_status_exit(406,'not acceptable');
		}

		if (! Config::Get('system','activitypub',ACTIVITYPUB_ENABLED)) {
			logger('ActivityPub INBOX request - protocol is disabled');
			http_status_exit(404,'Not found');
		}

		$sys_disabled = ((Config::Get('system','disable_discover_tab') || Config::Get('system','disable_activitypub_discover_tab'))  ? true : false);

		logger('inbox_args: ' . print_r(App::$argv,true));
		
		$is_public = false;

		if (argc() == 1 || argv(1) === '[public]') {
			$is_public = true;
		}
		else {
			$c = channelx_by_nick(argv(1));
			if (! $c) {
				http_status_exit(410,'Gone');
			}
			$channels = [ $c ];
		}

		$data = file_get_contents('php://input');
		if (! $data) {
			return;
		}

		logger('inbox_activity: ' . jindent($data), LOGGER_DATA);

		$hsig = HTTPSig::verify($data);

		// By convention, fediverse server-to-server communications require a valid HTTP Signature
		// which includes a signed digest header.
		
		// This check may need to move elsewhere or be modified in order to fully implement ActivityPub C2S.
		
		if (! ($hsig['header_signed'] && $hsig['header_valid'] && $hsig['content_signed'] && $hsig['content_valid'])) {
			http_status_exit(403,'Permission denied');
		}

		$AS = new ActivityStreams($data);
		if ($AS->is_valid() && $AS->type === 'Announce' && is_array($AS->obj)
			&& array_key_exists('object',$AS->obj) && array_key_exists('actor',$AS->obj)) {
			// This is a relayed/forwarded Activity (as opposed to a shared/boosted object)
			// Reparse the encapsulated Activity and use that instead
			logger('relayed activity',LOGGER_DEBUG);
			$AS = new ActivityStreams($AS->obj);
		}

		// logger('debug: ' . $AS->debug());

		if (! $AS->is_valid()) {
			if ($AS->deleted) {
				// process mastodon user deletion activities, but only if we can validate the signature
				if ($hsig['header_valid'] && $hsig['content_valid'] && $hsig['portable_id']) {
					logger('removing deleted actor');
					remove_all_xchan_resources($hsig['portable_id']);
				}
				else {
					logger('ignoring deleted actor', LOGGER_DEBUG, LOG_INFO);		
				}
			}
			return;
		}
		

		if (is_array($AS->actor) && array_key_exists('id',$AS->actor)) {
			Activity::actor_store($AS->actor['id'],$AS->actor);
		}

		if (is_array($AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])) {
			Activity::actor_store($AS->obj['id'],$AS->obj);
		}

		if (is_array($AS->obj) && is_array($AS->obj['actor']) && array_key_exists('id',$AS->obj['actor']) && $AS->obj['actor']['id'] !== $AS->actor['id']) {
			Activity::actor_store($AS->obj['actor']['id'],$AS->obj['actor']);
			if (! check_channelallowed($AS->obj['actor']['id'])) {
				http_status_exit(403,'Permission denied');
			}
		}

		// Validate that the channel that sent us this activity has authority to do so.
		// Require a valid HTTPSignature with a signed Digest header.

		// Only permit relayed activities if the activity is signed with LDSigs
		// AND the signature is valid AND the signer is the actor.

		if ($hsig['header_valid'] && $hsig['content_valid'] && $hsig['portable_id']) {

			// if the sender has the ability to send messages over zot/nomad, ignore messages sent via activitypub
			// as observer aware features and client side markup will be unavailable
			
			$test = Activity::get_actor_hublocs($hsig['portable_id'],'all,not_deleted');
			if ($test) {
				foreach ($test as $t) {
					if ($t['hubloc_network'] === 'zot6') {
						http_status_exit(409,'Conflict');
					}
				}
			}

			// fetch the portable_id for the actor, which may or may not be the sender
			
			$v = Activity::get_actor_hublocs($AS->actor['id'],'activitypub,not_deleted');

			if ($v && $v[0]['hubloc_hash'] !== $hsig['portable_id']) {
				// The sender is not actually the activity actor, so verify the LD signature.
				// litepub activities (with no LD signature) will always have a matching actor and sender
				
				if ($AS->signer && is_array($AS->signer) && $AS->signer['id'] !== $AS->actor['id'])  {
					// the activity wasn't signed by the activity actor
					return;
				}
				if (! $AS->sigok) {
					// The activity signature isn't valid.
					return;
				}
				
			}
			
			if ($v) {
				// The sender has been validated and stored
				$observer_hash = $hsig['portable_id'];
			}

		}

		if (! $observer_hash) {
			return;
		}

		// verify that this site has permitted communication with the sender.
		
		$m = parse_url($observer_hash);

		if ($m && $m['scheme'] && $m['host']) {
			if (! check_siteallowed($m['scheme'] . '://' . $m['host'])) {
				http_status_exit(403,'Permission denied');
			}
			// this site obviously isn't dead because they are trying to communicate with us. 
			$test = q("update site set site_dead = 0 where site_dead = 1 and site_url = '%s' ",
				dbesc($m['scheme'] . '://' . $m['host'])
			);
		}
		if (! check_channelallowed($observer_hash)) {
			http_status_exit(403,'Permission denied');
		}

		// update the hubloc_connected timestamp, ignore failures
		
		$test = q("update hubloc set hubloc_connected = '%s' where hubloc_hash = '%s' and hubloc_network = 'activitypub'",
			dbesc(datetime_convert()),
			dbesc($observer_hash)
		);


		// Now figure out who the recipients are

		if ($is_public) {

			if (in_array($AS->type, [ 'Follow', 'Join' ]) && is_array($AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])) {
				$channels = q("SELECT * from channel where channel_address = '%s' and channel_removed = 0 ",
					dbesc(basename($AS->obj['id']))
				);
			}
			else {
				// deliver to anybody following $AS->actor

				$channels = q("SELECT * from channel where channel_id in ( SELECT abook_channel from abook left join xchan on abook_xchan = xchan_hash WHERE xchan_network = 'activitypub' and xchan_hash = '%s' ) and channel_removed = 0 ",
					dbesc($observer_hash)
				);
				if (! $channels) {
					$channels = [];
				}

				$parent = $AS->parent_id;
				if ($parent) {
					// this is a comment - deliver to everybody who owns the parent
	 				$owners = q("SELECT * from channel where channel_id in ( SELECT uid from item where mid = '%s' ) ",
						dbesc($parent)
					);
					if ($owners) {
						$channels = array_merge($channels,$owners);
					}
				}
			}

			if ($channels === false) {
				$channels = [];
			}

			if (in_array(ACTIVITY_PUBLIC_INBOX,$AS->recips) || in_array('Public',$AS->recips) || in_array('as:Public',$AS->recips)) {

				// look for channels with send_stream = PERMS_PUBLIC (accept posts from anybody on the internet)

				$r = q("select * from channel where channel_id in (select uid from pconfig where cat = 'perm_limits' and k = 'send_stream' and v = '1' ) and channel_removed = 0 ");
				if ($r) {
					$channels = array_merge($channels,$r);
				}

				// look for channels that are following hashtags. These will be checked in tgroup_check()
				
				$r = q("select * from channel where channel_id in (select uid from pconfig where cat = 'system' and k = 'followed_tags' and v != '' ) and channel_removed = 0 ");
				if ($r) {
					$channels = array_merge($channels,$r);
				}


				if (! $sys_disabled) {
					$channels[] = get_sys_channel();
				}

			}

		}

		// $channels represents all "potential" recipients. If they are not in this array, they will not receive the activity.
		// If they are in this array, we will decide whether or not to deliver on a case-by-case basis.
 
		if (! $channels) {
			logger('no deliveries on this site');
			return;
		}

		// Bto and Bcc will only be present in a C2S transaction and should not be stored. 
		
		$saved_recips = [];
		foreach ( [ 'to', 'cc', 'audience' ] as $x ) {
			if (array_key_exists($x,$AS->data)) {
				$saved_recips[$x] = $AS->data[$x];
			}
		}
		$AS->set_recips($saved_recips);


		foreach ($channels as $channel) {

			// Even though activitypub may be enabled for the site, check if the channel has specifically disabled it
			if (! PConfig::Get($channel['channel_id'],'system','activitypub',Config::Get('system','activitypub',ACTIVITYPUB_ENABLED))) {
				continue;
			}
			
			logger('inbox_channel: ' . $channel['channel_address'],LOGGER_DEBUG);

			switch ($AS->type) {
				case 'Follow':
					if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])) {
						// do follow activity
						Activity::follow($channel,$AS);
					}
					break;
				case 'Invite':
					if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Group') {
						// do follow activity
						Activity::follow($channel,$AS);
					}
					break;
				case 'Join':
					if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Group') {
						// do follow activity
						Activity::follow($channel,$AS);
					}
					break;
				case 'Accept':			
					// Activitypub for wordpress sends lowercase 'follow' on accept.
					// https://github.com/pfefferle/wordpress-activitypub/issues/97
					// Mobilizon sends Accept/"Member" (not in vocabulary) in response to Join/Group
					if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && in_array($AS->obj['type'], ['Follow','follow', 'Member'])) {
						// do follow activity
						Activity::follow($channel,$AS);
					}
					break;

				case 'Reject':

				default:
					break;

			}

			// These activities require permissions		

			$item = null;

			switch ($AS->type) {
				case 'Update':
					if (is_array($AS->obj) && array_key_exists('type',$AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])) {
						Activity::actor_store($AS->obj['id'],$AS->obj, true /* force cache refresh */);
						break;
					}
				case 'Accept':
					if (is_array($AS->obj) && array_key_exists('type',$AS->obj) && (ActivityStreams::is_an_actor($AS->obj['type']) || $AS->obj['type'] === 'Member')) {
						break;
					}
				case 'Create':
				case 'Like':
				case 'Dislike':
				case 'Announce':
				case 'Reject':
				case 'TentativeAccept':
				case 'TentativeReject':
				case 'Add':
				case 'Arrive':
				case 'Block':
				case 'Flag':
				case 'Ignore':
				case 'Invite':
				case 'Listen':
				case 'Move':
				case 'Offer':
				case 'Question':
				case 'Read':
				case 'Travel':
				case 'View':
				case 'emojiReaction':
				case 'EmojiReaction':
				case 'EmojiReact':
					// These require a resolvable object structure
					if (is_array($AS->obj)) {
						// The boolean flag enables html cache of the item
						$item = Activity::decode_note($AS,true);
					}
					else {
						logger('unresolved object: ' . print_r($AS->obj,true));
					}
					break;
				case 'Undo':
					if ($AS->obj && is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Follow') {
						// do unfollow activity
						Activity::unfollow($channel,$AS);
						break;
					}
				case 'Leave':
					if ($AS->obj && is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Group') {
						// do unfollow activity
						Activity::unfollow($channel,$AS);
						break;
					}
				case 'Tombstone':
				case 'Delete':
					Activity::drop($channel,$observer_hash,$AS);
					break;

				case 'Move':
					if($observer_hash && $observer_hash === $AS->actor
						&& is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStream::is_an_actor($AS->obj['type'])
						&& is_array($AS->tgt) && array_key_exists('type', $AS->tgt) && ActivityStream::is_an_actor($AS->tgt['type'])) {
						ActivityPub::move($AS->obj,$AS->tgt);
					}
					break;
				case 'Add':
				case 'Remove':

					// for writeable collections as target, it's best to provide an array and include both the type and the id in the target element.
					// If it's just a string id, we'll try to fetch the collection when we receive it and that's wasteful since we don't actually need
					// the contents. 
					if (is_array($AS->obj) && isset($AS->tgt)) {
						// The boolean flag enables html cache of the item
						$item = Activity::decode_note($AS,true);
						break;
					}
				default:
					break;

			}

			if ($item) {
				logger('parsed_item: ' . print_r($item,true),LOGGER_DATA);
				Activity::store($channel,$observer_hash,$AS,$item);
			}

		}

		http_status_exit(200,'OK');
	}

	function get() {

	}

}



