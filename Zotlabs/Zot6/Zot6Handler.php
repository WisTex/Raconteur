<?php

namespace Zotlabs\Zot6;

use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Queue;

class Zot6Handler implements IHandler {

	function Notify($data,$hub) {
		return self::reply_notify($data,$hub);
	}

	function Request($data,$hub) {
		return self::reply_message_request($data,$hub);
	}

	function Rekey($sender,$data,$hub) {
		return self::reply_rekey_request($sender,$data,$hub);
	}

	function Refresh($sender,$recipients,$hub) {
		return self::reply_refresh($sender,$recipients,$hub);
	}

	function Purge($sender,$recipients,$hub) {
		return self::reply_purge($sender,$recipients,$hub);
	}


	// Implementation of specific methods follows;
	// These generally do a small amout of validation and call Libzot 
	// to do any heavy lifting

	static function reply_notify($data,$hub) {

		$ret = [ 'success' => false ];

		logger('notify received from ' . $hub['hubloc_url']);

		$x = Libzot::fetch($data);
		$ret['delivery_report'] = $x;
	

		$ret['success'] = true;
		return $ret;
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

	static function reply_refresh($sender, $recipients,$hub) {
		$ret = array('success' => false);

		if($recipients) {

			// This would be a permissions update, typically for one connection

			foreach ($recipients as $recip) {
				$r = q("select channel.*,xchan.* from channel
					left join xchan on channel_hash = xchan_hash
					where channel_hash ='%s' limit 1",
					dbesc($recip)
				);

				$x = Libzot::refresh( [ 'hubloc_id_url' => $hub['hubloc_id_url'] ], $r[0], (($msgtype === 'force_refresh') ? true : false));
			}
		}
		else {
			// system wide refresh

			$x = Libzot::refresh( [ 'hubloc_id_url' => $hub['hubloc_id_url'] ], null, (($msgtype === 'force_refresh') ? true : false));
		}

		$ret['success'] = true;
		return $ret;
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
	
	static function reply_message_request($data,$hub) {
		$ret = [ 'success' => false ];

		$message_id = EMPTY_STR;

		if(array_key_exists('data',$data))
		$ptr = $data['data'];
		if(is_array($ptr) && array_key_exists(0,$ptr)) {
			$ptr = $ptr[0];
		}
		if(is_string($ptr)) {
			$message_id = $ptr;
		}
		if(is_array($ptr) && array_key_exists('id',$ptr)) {
			$message_id = $ptr['id'];
		}

		if (! $message_id) {
			$ret['message'] = 'no message_id';
			logger('no message_id');
			return $ret;
		}

		$sender = $hub['hubloc_hash'];

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
			return $ret;
		}

		/*
		 * fetch the requested conversation
		 */

		$messages = zot_feed($c[0]['channel_id'],$sender_hash, [ 'message_id' => $data['message_id'], 'encoding' => 'activitystreams' ]);

		return (($messages) ? : [] );

	}

	static function rekey_request($sender,$data,$hub) {

		$ret = array('success' => false);

		//	newsig is newkey signed with oldkey

		// The original xchan will remain. In Zot/Receiver we will have imported the new xchan and hubloc to verify
		// the packet authenticity. What we will do now is verify that the keychange operation was signed by the
		// oldkey, and if so change all the abook, abconfig, group, and permission elements which reference the
		// old xchan_hash.

		if((! $data['old_key']) && (! $data['new_key']) && (! $data['new_sig']))
			return $ret;


		$old = null;

		if(Libzot::verify($data['old_guid'],$data['old_guid_sig'],$data['old_key'])) {
			$oldhash = make_xchan_hash($data['old_guid'],$data['old_key']);
			$old = q("select * from xchan where xchan_hash = '%s' limit 1",
				dbesc($oldhash)
			);
		}
		else 
			return $ret;


		if(! $old) {
			return $ret;
		}

		$xchan = $old[0];

		if(! Libzot::verify($data['new_key'],$data['new_sig'],$xchan['xchan_pubkey'])) {
			return $ret;
		}

		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($sender)
		);

		$newxchan = $r[0];

		// @todo
		// if ! $update create a linked identity


		xchan_change_key($xchan,$newxchan,$data);

		$ret['success'] = true;
		return $ret;
	}


	/**
	 * @brief
	 *
	 * @param array $sender
	 * @param array $recipients
	 *
	 * return json_return_and_die()
	 */

	static function reply_purge($sender, $recipients, $hub) {

		$ret = array('success' => false);

		if ($recipients) {
			// basically this means "unfriend"
			foreach ($recipients as $recip) {
				$r = q("select channel.*,xchan.* from channel
					left join xchan on channel_hash = xchan_hash
					where channel_hash = '%s' and channel_guid_sig = '%s' limit 1",
					dbesc($recip)
				);
				if ($r) {
					$r = q("select abook_id from abook where uid = %d and abook_xchan = '%s' limit 1",
						intval($r[0]['channel_id']),
						dbesc($sender)
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

			remove_all_xchan_resources($sender);

			$ret['success'] = true;
		}

		return $ret;
	}






}
