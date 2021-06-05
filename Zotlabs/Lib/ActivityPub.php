<?php
namespace Zotlabs\Lib;

use Zotlabs\Lib\LDSignatures;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Queue;
use Zotlabs\Lib\Libsync;
use Zotlabs\Daemon\Run;
use Zotlabs\Lib\IConfig;

class ActivityPub {

	static public function notifier_process(&$arr) {

		if ($arr['hub']['hubloc_network'] !== 'activitypub') {
			return;
		}

		logger('upstream: ' . intval($arr['upstream']));

		// logger('notifier_array: ' . print_r($arr,true), LOGGER_ALL, LOG_INFO);

		$purge_all = (($arr['packet_type'] === 'purge' && (! intval($arr['private']))) ? true : false);
		
		$signed_msg = null;

		if (array_key_exists('target_item',$arr) && is_array($arr['target_item'])) {

			if (intval($arr['target_item']['item_obscured'])) {
				logger('Cannot send raw data as an activitypub activity.');
				return;
			}

			$signed_msg = get_iconfig($arr['target_item'],'activitypub','rawmsg');

			// If we have an activity already stored with an LD-signature
			// which we are sending downstream, use that signed activity as is.
			// The channel will then sign the HTTP transaction. 

			// It is unclear if Mastodon supports the federation delivery model. Initial tests were
			// inconclusive and the behaviour varied. 

			if (($arr['channel']['channel_hash'] !== $arr['target_item']['author_xchan']) && (! $signed_msg)) {
				logger('relayed post with no signed message');
				return;
			}
		
		}

		if ($purge_all) {

			$ti = [
				'id' => channel_url($arr['channel']) . '#delete',
				'actor' => channel_url($arr['channel']),
				'type' => 'Delete',
				'object' => channel_url($arr['channel']),
				'to' => [ 'https://www.w3.org/ns/activitystreams#Public' ]
			];

			$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				Activity::ap_schema()
			]], $ti);

			$msg['signature'] = LDSignatures::sign($msg,$arr['channel']);

			logger('ActivityPub_encoded (purge_all): ' . json_encode($msg,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
			
			$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);
			
		}
		else {
			$target_item = $arr['target_item'];

			if (! $target_item['mid']) {
				return;
			}

			$prv_recips = $arr['env_recips'];


			if ($signed_msg) {
				$jmsg = $signed_msg;
			}
			else {

				// Rewrite outbound mentions so they match the ActivityPub convention, which
				// is to pretend that the preferred display name doesn't exist and instead use
				// the username or webfinger address when displaying names. This is likely to
				// only cause confusion on nomadic networks where there could be any number
				// of applicable webfinger addresses for a given identity. 


				Activity::rewrite_mentions_sub($target_item, 1, $target_item['obj']);

				$ti = Activity::encode_activity($target_item, true);

				if (! $ti) {
					return;
				}

				$token = IConfig::get($target_item['id'],'ocap','relay');
				if ($token) {
					if (defined('USE_BEARCAPS')) {
						$ti['id'] = 'bear:?u=' . $ti['id'] . '&t=' . $token;
					}
					else {
						$ti['id'] = $ti['id'] . '?token=' . $token;
					}
					if ($ti['url'] && is_string($ti['url'])) {
						$ti['url'] .= '?token=' . $token;
					}
				}

				$msg = array_merge(['@context' => [
					ACTIVITYSTREAMS_JSONLD_REV,
					'https://w3id.org/security/v1',
					Activity::ap_schema()
				]], $ti);
	
				$msg['signature'] = LDSignatures::sign($msg,$arr['channel']);

				logger('ActivityPub_encoded: ' . json_encode($msg,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
			
				$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);
			}
		}
		if ($prv_recips) {
			$hashes = [];

			// re-explode the recipients, but only for this hub/pod

			foreach ($prv_recips as $recip) {
				$hashes[] = "'" . $recip . "'";
			}

			$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s'
				and xchan_hash in (" . implode(',', $hashes) . ") and xchan_network = 'activitypub' ",
				dbesc($arr['hub']['hubloc_url'])
			);

			if (! $r) {
				logger('activitypub_process_outbound: no recipients');
				return;
			}

			foreach ($r as $contact) {

				// is $contact connected with this channel - and if the channel is cloned, also on this hub?
				// 2018-10-19 this probably doesn't apply to activitypub anymore, just send the thing.
				// They'll reject it if they don't like it. 
				// $single = deliverable_singleton($arr['channel']['channel_id'],$contact);

				if (! $arr['normal_mode']) {
					continue;
				}

				$qi = self::queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
				if ($qi) {
					$arr['queued'][] = $qi;
				}
				continue;
			}

		}
		else {

			// public message

			// See if we can deliver all of them at once

			$x = get_xconfig($arr['hub']['hubloc_hash'],'activitypub','collections');
			if ($x && $x['sharedInbox']) {
				logger('using publicInbox delivery for ' . $arr['hub']['hubloc_url'], LOGGER_DEBUG);
				$contact['hubloc_callback'] = $x['sharedInbox'];
				$qi = self::queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
				if ($qi) {
					$arr['queued'][] = $qi;
				}
			}
			else {

				$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s' and xchan_network = 'activitypub' ",
					dbesc($arr['hub']['hubloc_url'])
				);

				if (! $r) {
					logger('activitypub_process_outbound: no recipients');
					return;
				}
		
				foreach ($r as $contact) {

					// $single = deliverable_singleton($arr['channel']['channel_id'],$contact);

					$qi = self::queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
					if ($qi) {
						$arr['queued'][] = $qi;
					}
				}	
			}
		}
	
		return;

	}


	static function queue_message($msg,$sender,$recip,$message_id = '') {

		$dest_url = $recip['hubloc_callback'];

    	logger('URL: ' . $dest_url, LOGGER_DEBUG);
		logger('DATA: ' . jindent($msg), LOGGER_DATA);

    	if (intval(get_config('system','activitypub_test')) || intval(get_pconfig($sender['channel_id'],'system','activitypub_test'))) {
        	logger('test mode - delivery disabled');
	        return false;
    	}

	    $hash = random_string();

    	logger('queue: ' . $hash . ' ' . $dest_url, LOGGER_DEBUG);
		Queue::insert([
    	    'hash'       => $hash,
        	'account_id' => $sender['channel_account_id'],
	        'channel_id' => $sender['channel_id'],
    	    'driver'     => 'activitypub',
        	'posturl'    => $dest_url,
	        'notify'     => '',
    	    'msg'        => $msg
    	]);

	    if ($message_id && (! get_config('system','disable_dreport'))) {
    	    q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_result, dreport_time, dreport_xchan, dreport_queue ) values ( '%s','%s','%s','%s','%s','%s','%s' ) ",
        	    dbesc($message_id),
            	dbesc($dest_url),
	            dbesc($dest_url),
    	        dbesc('queued'),
        	    dbesc(datetime_convert()),
            	dbesc($sender['channel_hash']),
	            dbesc($hash)
    	    );
    	}

	    return $hash;
	}


	static function permissions_update(&$x) {

		if ($x['recipient']['xchan_network'] !== 'activitypub') {
			return;
		}
		self::discover($x['recipient']['xchan_hash'],true);
		$x['success'] = true;
	}


	static function permissions_create(&$x) {

		// send a follow activity to the followee's inbox

		if ($x['recipient']['xchan_network'] !== 'activitypub') {
			return;
		}

		$p = Activity::encode_person($x['sender'],false);
		if (! $p) {
			return;
		}

		$orig_follow = get_abconfig($x['sender']['channel_id'],$x['recipient']['xchan_hash'],'activitypub','their_follow_id');
		$orig_follow_type = get_abconfig($x['sender']['channel_id'],$x['recipient']['xchan_hash'],'activitypub','their_follow_type');

		$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				Activity::ap_schema()
			]], 
			[
				'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'] . (($orig_follow) ? '/' . md5($orig_follow) : EMPTY_STR),
				'type'   => (($orig_follow_type) ? $orig_follow_type : 'Follow'),
				'actor'  => $p,
				'object' => $x['recipient']['xchan_hash'],
				'to'     => [ $x['recipient']['xchan_hash'] ]
		]);

		// for Group actors, send both a Follow and a Join because some platforms only support one and there's
		// no way of discovering/knowing in advance which type they support

		$join_msg = null;

		if (intval($x['recipient']['xchan_type']) === 1) {
			$join_msg = $msg;
			$join_msg['type'] = 'Join';
			$join_msg['signature'] = LDSignatures::sign($join_msg,$x['sender']);
			$jmsg2 = json_encode($join_msg, JSON_UNESCAPED_SLASHES);
		}

		$msg['signature'] = LDSignatures::sign($msg,$x['sender']);
		$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

		$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
			dbesc($x['recipient']['xchan_hash'])
		);

		if ($h) {
			$qi = self::queue_message($jmsg,$x['sender'],$h[0]);
			if ($qi) {
				$x['deliveries'] = $qi;
			}
			if ($join_msg) {
				$qi = self::queue_message($jmsg2,$x['sender'],$h[0]);
				if ($qi) {
					$x['deliveries'] = $qi;
				}
			}
		}
		
		$x['success'] = true;
	}


	static function permissions_accept(&$x) {

		// send an accept activity to the followee's inbox

		if ($x['recipient']['xchan_network'] !== 'activitypub') {
			return;
		}

		// we currently are not handling send of reject follow activities; this is permitted by protocol

		$accept = get_abconfig($x['recipient']['abook_channel'],$x['recipient']['xchan_hash'],'activitypub','their_follow_id');
		$follow_type = get_abconfig($x['recipient']['abook_channel'],$x['recipient']['xchan_hash'],'activitypub','their_follow_type');
		if (! $accept) {
			return;
		}

		$p = Activity::encode_person($x['sender'],false);
		if (! $p) {
			return;
		}

		$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				Activity::ap_schema()
			]], 
			[
				'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'] . '/' . md5($accept),
				'type'   => 'Accept',
				'actor'  => $p,
				'object' => [
					'type'   => (($follow_type) ? $follow_type : 'Follow'),
					'id'     => $accept,
					'actor'  => $x['recipient']['xchan_hash'],
					'object' => z_root() . '/channel/' . $x['sender']['channel_address']
				],
				'to' => [ $x['recipient']['xchan_hash'] ]
		]);

		$msg['signature'] = LDSignatures::sign($msg,$x['sender']);

		$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

		$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
			dbesc($x['recipient']['xchan_hash'])
		);

		if ($h) {
			$qi = self::queue_message($jmsg,$x['sender'],$h[0]);
			if ($qi) {
				$x['deliveries'] = $qi;
			}
		}
		
		$x['success'] = true;

	}

	static function contact_remove($channel_id,$abook) {

		$recip = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
			intval($abook['abook_id'])
		);

		if ((! $recip) || $recip[0]['xchan_network'] !== 'activitypub')
			return; 

		$channel = channelx_by_n($recip[0]['abook_channel']);
		if (! $channel) {
			return;
		}

		$p = Activity::encode_person($channel,true,true);
		if (! $p) {
			return;
		}

		// send an unfollow activity to the followee's inbox

		$orig_activity = get_abconfig($recip[0]['abook_channel'],$recip[0]['xchan_hash'],'activitypub','follow_id');

		if ($orig_activity && $recip[0]['abook_pending']) {

			// was never approved

			$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				Activity::ap_schema()
			]], 
			[
				'id'    => z_root() . '/follow/' . $recip[0]['abook_id'] . '/' . md5($orig_activity) . '#reject',
				'type'  => 'Reject',
				'actor' => $p,
				'object'     => [
					'type'   => 'Follow',
					'id'     => $orig_activity,
					'actor'  => $recip[0]['xchan_hash'],
					'object' => $p
				],
				'to' => [ $recip[0]['xchan_hash'] ]
			]);
			del_abconfig($recip[0]['abook_channel'],$recip[0]['xchan_hash'],'activitypub','follow_id');

		}
		else {

			// send an unfollow

			$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				Activity::ap_schema()
			]], 
			[
				'id'    => z_root() . '/follow/' . $recip[0]['abook_id'] . (($orig_activity) ? '/' . md5($orig_activity) : EMPTY_STR) . '#Undo',
				'type'  => 'Undo',
				'actor' => $p,
				'object'     => [
					'id'     => z_root() . '/follow/' . $recip[0]['abook_id'] . (($orig_activity) ? '/' . md5($orig_activity) : EMPTY_STR),
					'type'   => 'Follow',
					'actor'  => $p,
					'object' => $recip[0]['xchan_hash']
				],
				'to' => [ $recip[0]['xchan_hash'] ]
			]);
		}

		$msg['signature'] = LDSignatures::sign($msg,$channel);

		$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

		$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
			dbesc($recip[0]['xchan_hash'])
		);

		if ($h) {
			$qi = self::queue_message($jmsg,$channel,$h[0]);
			if ($qi) {
				Run::Summon([ 'Deliver' , $qi ]);
			}
		}	
	}

	static function discover($apurl, $force = false) {

		$person_obj = null;
		$ap = Activity::fetch($apurl);
		if ($ap) {
			$AS = new ActivityStreams($ap); 
			if ($AS->is_valid()) {
				if (ActivityStreams::is_an_actor($AS->type)) {
					$person_obj = $AS->data;
				}
				elseif ($AS->obj && ActivityStreams::is_an_actor($AS->obj['type'])) {
					$person_obj = $AS->obj;
				}
			}
		}
		if (isset($person_obj)) {
			Activity::actor_store($person_obj['id'],$person_obj, $force);
			return $person_obj['id'];
		}
		return false;
	}

	static public function move($src,$dst) {

		if (! ($src && $dst)) {
			return;
		}

		if ($src && ! is_array($src)) {
			$src = Activity::fetch($src);
			if (is_array($src)) {
				$src_xchan = $src['id'];
			}
		}

		$approvals = null;

		if ($dst && ! is_array($dst)) {
			$dst = Activity::fetch($dst);
			if (is_array($dst)) {
				$dst_xchan = $dst['id'];
				if (array_key_exists('alsoKnownAs',$dst)) {
					if(! is_array($dst['alsoKnownAs'])) {
						$dst['alsoKnownAs'] = [ $dst['alsoKnownAs'] ];
					}
					$approvals = $dst['alsoKnownAs'];
				}
			}
		}

		if(! ($src_xchan && $dst_xchan)) {
			return;
		}

		if ($approvals) {
			foreach($approvals as $approval) {
				if($approval === $src_xchan) {
					$abooks = q("select abook_channel from abook where abook_xchan = '%s'",
						dbesc($src_xchan)
					);
					if ($abooks) {
						foreach ($abooks as $abook) {
							// check to see if we already performed this action
							$x = q("select * from abook where abook_xchan = '%s' and abook_channel = %d",
								dbesc($dst_xchan),
								intval($abook['abook_channel'])
							);
							if ($x) {
								continue;
							}
							// update the local abook							
							q("update abconfig set xchan = '%s' where chan = %d and xchan = '%s'",
								dbesc($dst_xchan),
								intval($abook['abook_channel']),
								dbesc($src_xchan)
							);
							q("update pgrp_member set xchan = '%s' where uid = %d and xchan = '%s'",
								dbesc($dst_xchan),
								intval($abook['abook_channel']),
								dbesc($src_xchan)
							);							
							$r = q("update abook set abook_xchan = '%s' where abook_xchan = '%s' and abook_channel = %d ",
								dbesc($dst_xchan),
								dbesc($src_xchan),
								intval($abook['abook_channel'])
							);

							$r = q("SELECT abook.*, xchan.*
								FROM abook left join xchan on abook_xchan = xchan_hash
								WHERE abook_channel = %d and abook_id = %d LIMIT 1",
								intval(abook['abook_channel']),
								intval($dst_xchan)
							);
							if ($r) {
								$clone = array_shift($r);
								unset($clone['abook_id']);
								unset($clone['abook_account']);
								unset($clone['abook_channel']);
								$abconfig = load_abconfig($abook['abook_channel'],$clone['abook_xchan']);
								if ($abconfig) {
									$clone['abconfig'] = $abconfig;
								}
								Libsync::build_sync_packet($abook['abook_channel'], [ 'abook' => [ $clone ] ] );
							}
						}
					}
				}
			}
		}
	}

}