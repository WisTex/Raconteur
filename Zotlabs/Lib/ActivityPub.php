<?php
namespace Zotlabs\Lib;

use Zotlabs\Lib\LDSignatures;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Queue;
use Zotlabs\Daemon\Master;


class ActivityPub {

	static public function notifier_process(&$arr) {

		if($arr['hub']['hubloc_network'] !== 'activitypub')
			return;

		logger('upstream: ' . intval($arr['upstream']));

		logger('notifier_array: ' . print_r($arr,true), LOGGER_ALL, LOG_INFO);


		$signed_msg = null;

		if(array_key_exists('target_item',$arr) && is_array($arr['target_item'])) {

			if(intval($arr['target_item']['item_obscured'])) {
				logger('Cannot send raw data as an activitypub activity.');
				return;
			}

			if(strpos($arr['target_item']['postopts'],'nopub') !== false) {
				return;
			}

			$signed_msg = get_iconfig($arr['target_item'],'activitypub','rawmsg');

			// If we have an activity already stored with an LD-signature
			// which we are sending downstream, use that signed activity as is.
			// The channel will then sign the HTTP transaction. 

			// It is unclear if Mastodon supports the federation delivery model. Initial tests were
			// inconclusive and the behaviour varied. 

			if(($arr['channel']['channel_hash'] != $arr['target_item']['author_xchan']) && (! $signed_msg)) {
				return;
			}
		
		}

		$target_item = $arr['target_item'];

		if(! $target_item['mid'])
			return;

		$prv_recips = $arr['env_recips'];


		if($signed_msg) {
			$jmsg = $signed_msg;
		}
		else {
			$ti = Activity::encode_activity($target_item, true);
			if(! $ti)
				return;

			$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]], $ti);
	
			$msg['signature'] = LDSignatures::sign($msg,$arr['channel']);

			logger('ActivityPub_encoded: ' . json_encode($msg,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));

			$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);
		}

		if($prv_recips) {
			$hashes = array();

			// re-explode the recipients, but only for this hub/pod

			foreach($prv_recips as $recip)
				$hashes[] = "'" . $recip . "'";

			$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s'
				and xchan_hash in (" . implode(',', $hashes) . ") and xchan_network = 'activitypub' ",
				dbesc($arr['hub']['hubloc_url'])
			);

			if(! $r) {
				logger('activitypub_process_outbound: no recipients');
				return;
			}

			foreach($r as $contact) {

				// is $contact connected with this channel - and if the channel is cloned, also on this hub?
				// 2018-10-19 this probably doesn't apply to activitypub anymore, just send the thing.
				// They'll reject it if they don't like it. 
				// $single = deliverable_singleton($arr['channel']['channel_id'],$contact);

				if(! $arr['normal_mode'])
					continue;

				$qi = self::queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
				if($qi) {
					$arr['queued'][] = $qi;
				}
				continue;
			}

		}
		else {

			// public message

			// See if we can deliver all of them at once

			$x = get_xconfig($arr['hub']['hubloc_hash'],'activitypub','collections');
			if($x && $x['sharedInbox']) {
				logger('using publicInbox delivery for ' . $arr['hub']['hubloc_url'], LOGGER_DEBUG);
				$contact['hubloc_callback'] = $x['sharedInbox'];
				$qi = self::queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
				if($qi) {
					$arr['queued'][] = $qi;
				}
			}
			else {

				$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s' and xchan_network = 'activitypub' ",
					dbesc($arr['hub']['hubloc_url'])
				);

				if(! $r) {
					logger('activitypub_process_outbound: no recipients');
					return;
				}

		
				foreach($r as $contact) {

					// $single = deliverable_singleton($arr['channel']['channel_id'],$contact);


					$qi = self::queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
					if($qi) {
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

    	if(intval(get_config('system','activitypub_test')) || intval(get_pconfig($sender['channel_id'],'system','activitypub_test'))) {
        	logger('test mode - delivery disabled');
	        return false;
    	}

	    $hash = random_string();

    	logger('queue: ' . $hash . ' ' . $dest_url, LOGGER_DEBUG);
		Queue::insert(array(
    	    'hash'       => $hash,
        	'account_id' => $sender['channel_account_id'],
	        'channel_id' => $sender['channel_id'],
    	    'driver'     => 'activitypub',
        	'posturl'    => $dest_url,
	        'notify'     => '',
    	    'msg'        => $msg
    	));

	    if($message_id && (! get_config('system','disable_dreport'))) {
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



	static function permissions_create(&$x) {

		// send a follow activity to the followee's inbox

		if($x['recipient']['xchan_network'] !== 'activitypub') {
			return;
		}

		$p = Activity::encode_person($x['sender'],true,true);
		if(! $p)
			return;

		$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]], 
			[
				'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'],
				'type'   => 'Follow',
				'actor'  => $p,
				'object' => $x['recipient']['xchan_url'],
				'to'     => [ $x['recipient']['xchan_hash'] ]
		]);


		$msg['signature'] = LDSignatures::sign($msg,$x['sender']);

		$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

		$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
			dbesc($x['recipient']['xchan_hash'])
		);

		if($h) {
			$qi = self::queue_message($jmsg,$x['sender'],$h[0]);
			if($qi)
				$x['deliveries'] = $qi;
		}
		
		$x['success'] = true;

	}


	static function permissions_accept(&$x) {

		// send an accept activity to the followee's inbox

		if($x['recipient']['xchan_network'] !== 'activitypub') {
			return;
		}

		// we currently are not handling send of reject follow activities; this is permitted by protocol

		$accept = get_abconfig($x['recipient']['abook_channel'],$x['recipient']['xchan_hash'],'activitypub','their_follow_id');
		if(! $accept)
			return;

		$p = Activity::encode_person($x['sender'],true,true);
		if(! $p)
			return;

		$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]], 
			[
				'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'],
				'type'   => 'Accept',
				'actor'  => $p,
				'object' => [
					'type'   => 'Follow',
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

		if($h) {
			$qi = self::queue_message($jmsg,$x['sender'],$h[0]);
			if($qi)
				$x['deliveries'] = $qi;
		}
		
		$x['success'] = true;

	}

	static function contact_remove($channel_id,$abook) {

		$recip = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
			intval($abook['abook_id'])
		);

		if((! $recip) || $recip[0]['xchan_network'] !== 'activitypub')
			return; 

		$channel = channelx_by_n($recip[0]['abook_channel']);
		if(! $channel)
			return;

		$p = Activity::encode_person($channel,true,true);
		if(! $p)
			return;

		// send an unfollow activity to the followee's inbox

		$orig_activity = get_abconfig($recip[0]['abook_channel'],$recip[0]['xchan_hash'],'activitypub','follow_id');

		if($orig_activity && $recip[0]['abook_pending']) {


			// was never approved

			$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]], 
			[
				'id'    => z_root() . '/follow/' . $recip[0]['abook_id'] . '#reject',
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
				z_root() . ZOT_APSCHEMA_REV
			]], 
			[
				'id'    => z_root() . '/follow/' . $recip[0]['abook_id'] . '#Undo',
				'type'  => 'Undo',
				'actor' => $p,
				'object'     => [
					'id'     => z_root() . '/follow/' . $recip[0]['abook_id'],
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

		if($h) {
			$qi = self::queue_message($jmsg,$channel,$h[0]);
			if($qi) {
				Master::Summon([ 'Deliver' , $qi ]);
			}
		}	
	}

	static function discover($apurl) {

		$person_obj = null;
		$ap = ActivityStreams::fetch($apurl);
		if($ap) {
			$AS = new ActivityStreams($ap); 
			if($AS->is_valid()) {
				if(ActivityStreams::is_an_actor($AS->type)) {
					$person_obj = $AS->data;
				}
				elseif($AS->obj && ActivityStreams::is_an_actor($AS->obj['type'])) {
					$person_obj = $AS->obj;
				}
			}
		}
		if($person_obj) {
			Activity::actor_store($person_obj['id'],$person_obj);
			return $person_obj['id'];
		}
		return false;
	}

}