<?php

namespace Zotlabs\Zot6;

use Zotlabs\Lib\Libzot;
require_once('include/queue_fn.php');

class Zot6Handler implements IHandler {

	function Notify($data,$hubs) {
		self::reply_notify($data,$hubs);
	}

	function Request($data,$hubs) {
		self::reply_message_request($data,$hubs);
	}

	function Rekey($sender,$data,$hubs) {
		self::reply_rekey_request($sender,$data,$hubs);
	}

	function Refresh($sender,$recipients,$hubs) {
		self::reply_refresh($sender,$recipients,$hubs);
	}

	function Purge($sender,$recipients,$hubs) {
		self::reply_purge($sender,$recipients,$hubs);
	}

	// Implementation of specific methods follows;
	// These generally do a small amout of validation and call Libzot 
	// to do any heavy lifting

	static function reply_notify($data,$hubs) {

		$ret = [ 'success' => false ];

		logger('notify received from ' . $data['sender']['location']);

		$x = Libzot::fetch($data,$hubs);
		$ret['delivery_report'] = $x;
	

		$ret['success'] = true;
		json_return_and_die($ret);
	}



	/**
	 * @brief Remote channel info (such as permissions or photo or something)
	 * has been updated. Grab a fresh copy and sync it.
	 *
	 * The difference between refresh and force_refresh is that force_refresh
	 * unconditionally creates a directory update record, even if no changes were
	 * detected upon processing.
	 *
	 * @param array $sender
	 * @param array $recipients
	 *
	 * @return json_return_and_die()
	 */

	static function reply_refresh($sender, $recipients,$hubs) {
		$ret = array('success' => false);

		if($recipients) {

			// This would be a permissions update, typically for one connection

			foreach ($recipients as $recip) {
				$r = q("select channel.*,xchan.* from channel
					left join xchan on channel_hash = xchan_hash
					where channel_hash ='%s' limit 1",
					dbesc($recip['portable_id'])
				);

				$x = Libzot::refresh( [ 'hubloc_id_url' => $sender['id_url'] ], $r[0], (($msgtype === 'force_refresh') ? true : false));
			}
		}
		else {
			// system wide refresh

			$x = Libzot::refresh( [ 'hubloc_id_url' => $sender['id_url'] ], null, (($msgtype === 'force_refresh') ? true : false));
		}

		$ret['success'] = true;
		json_return_and_die($ret);
	}



	/**
	 * @brief Process a message request.
	 *
	 * If a site receives a comment to a post but finds they have no parent to attach it with, they
	 * may send a 'request' packet containing the message_id of the missing parent. This is the handler
	 * for that packet. We will create a message_list array of the entire conversation starting with
	 * the missing parent and invoke delivery to the sender of the packet.
	 *
	 * Zotlabs/Daemon/Deliver.php (for local delivery) and 
	 * mod/post.php???? @fixme (for web delivery) detect the existence of
	 * this 'message_list' at the destination and split it into individual messages which are
	 * processed/delivered in order.
	 *
	 *
	 * @param array $data
	 * @return array
	 */
	
	static function reply_message_request($data,$hubs) {
		$ret = [ 'success' => false ];

		if (! $data['message_id']) {
			$ret['message'] = 'no message_id';
			logger('no message_id');
			json_return_and_die($ret);
		}

		$sender = $data['sender'];
		$sender_hash = $hubs[0]['hubloc_hash'];

		/*
		 * Find the local channel in charge of this post (the first and only recipient of the request packet)
		 */

		$arr = $data['recipients'][0];

		$c = q("select * from channel left join xchan on channel_hash = xchan_hash where channel_hash = '%s' limit 1",
			dbesc($arr['portable_id'])
		);
		if (! $c) {
			logger('recipient channel not found.');
			$ret['message'] .= 'recipient not found.' . EOL;
			json_return_and_die($ret);
		}

		/*
		 * fetch the requested conversation
		 */

		$messages = zot_feed($c[0]['channel_id'],$sender_hash,array('message_id' => $data['message_id']));

		if ($messages) {
			$env_recips = null;

			$r = q("select hubloc.*, site.site_crypto from hubloc left join site on hubloc_url = site_url where hubloc_hash = '%s' and hubloc_error = 0 and hubloc_deleted = 0 and site.site_dead = 0 ",
				dbesc($sender_hash)
			);
			if (! $r) {
				logger('no hubs');
				json_return_and_die($ret);
			}
			$ohubs = $r;

			$private = ((array_key_exists('flags', $messages[0]) && in_array('private',$messages[0]['flags'])) ? true : false);
			if($private) {
				$env_recips = [ 'id' => $sender['id'], 'id_sig' => $sender['id_sig'], 'portable_id' => $sender_hash ];
			}

			$data_packet = json_encode(array('message_list' => $messages));

			foreach($ohubs as $hub) {
				$hash = random_string();

				/*
				 * create a notify packet and drop the actual message packet in the queue for pickup
				 */

				$n = Libzot::build_packet($c[0],'notify',$env_recips,$data_packet,(($private) ? $hub['hubloc_sitekey'] : null),$hub['site_crypto'],$hash,array('message_id' => $data['message_id']));

				queue_insert(array(
					'hash'       => $hash,
					'account_id' => $c[0]['channel_account_id'],
					'channel_id' => $c[0]['channel_id'],
					'posturl'    => $hub['hubloc_callback'],
					'notify'     => $n,
					'msg'        => $data_packet
				));


				$x = q("select count(outq_hash) as total from outq where outq_delivered = 0");
				if(intval($x[0]['total']) > intval(get_config('system','force_queue_threshold',300))) {
					logger('immediate delivery deferred.', LOGGER_DEBUG, LOG_INFO);
					update_queue_item($hash);
					continue;
				}

				/*
				 * invoke delivery to send out the notify packet
				 */

				\Zotlabs\Daemon\Master::Summon(array('Deliver', $hash));
			}
		}
		$ret['success'] = true;
		json_return_and_die($ret);
	}

	static function rekey_request($sender,$data,$hubs) {

		$ret = array('success' => false);

		//	newsig is newkey signed with oldkey

		// The original xchan will remain. In Zot/Receiver we will have imported the new xchan and hubloc to verify
		// the packet authenticity. What we will do now is verify that the keychange operation was signed by the
		// oldkey, and if so change all the abook, abconfig, group, and permission elements which reference the
		// old xchan_hash.

		if((! $data['old_key']) && (! $data['new_key']) && (! $data['new_sig']))
			json_return_and_die($ret);


		$old = null;

		if(Libzot::verify($data['old_guid'],$data['old_guid_sig'],$data['old_key'])) {
			$oldhash = make_xchan_hash($data['old_guid'],$data['old_key']);
			$old = q("select * from xchan where xchan_hash = '%s' limit 1",
				dbesc($oldhash)
			);
		}
		else 
			json_return_and_die($ret);


		if(! $old) {
			json_return_and_die($ret);
		}

		$xchan = $old[0];

		if(! Libzot::verify($data['new_key'],$data['new_sig'],$xchan['xchan_pubkey'])) {
			json_return_and_die($ret);
		}


		if(Libzot::verify($sender['id'],$sender['id_sig'],$data['new_key'])) {
			$newhash = make_xchan_hash($sender['id'],$data['new_key']);
		}

		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($newhash)
		);

		$newxchan = $r[0];

		xchan_change_key($xchan,$newxchan,$data);

		$ret['success'] = true;
		json_return_and_die($ret);
	}


	/**
	 * @brief
	 *
	 * @param array $sender
	 * @param array $recipients
	 *
	 * return json_return_and_die()
	 */

	static function reply_purge($sender, $recipients,$hubs) {

		$ret = array('success' => false);

		if ($recipients) {
			// basically this means "unfriend"
			foreach ($recipients as $recip) {
				$r = q("select channel.*,xchan.* from channel
					left join xchan on channel_hash = xchan_hash
					where channel_hash = '%s' and channel_guid_sig = '%s' limit 1",
					dbesc($recip['portable_id'])
				);
				if ($r) {
					$r = q("select abook_id from abook where uid = %d and abook_xchan = '%s' limit 1",
						intval($r[0]['channel_id']),
						dbesc($hubs[0]['hubloc_hash'])
					);
					if ($r) {
						contact_remove($r[0]['channel_id'],$r[0]['abook_id']);
					}
				}
			}
			$ret['success'] = true;
		}
		else {

			// Unfriend everybody - basically this means the channel has committed suicide

			remove_all_xchan_resources($hubs[0]['hubloc_hash']);

			$ret['success'] = true;
		}

		json_return_and_die($ret);
	}






}
