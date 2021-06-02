<?php

namespace Zotlabs\Zot6;

use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Queue;

class Zot6Handler implements IHandler {

	function Notify($data,$hub) {
		return self::reply_notify($data,$hub);
	}

	function Rekey($sender,$data,$hub) {
		return self::reply_rekey_request($sender,$data,$hub);
	}

	function Refresh($sender,$recipients,$hub,$force) {
		return self::reply_refresh($sender,$recipients,$hub,$force);
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

		$x = Libzot::fetch($data,$hub);
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

	static function reply_refresh($sender, $recipients,$hub,$force) {
		$ret = array('success' => false);

		if($recipients) {

			// This would be a permissions update, typically for one connection

			foreach ($recipients as $recip) {
				$r = q("select channel.*,xchan.* from channel
					left join xchan on channel_hash = xchan_hash
					where channel_hash ='%s' limit 1",
					dbesc($recip)
				);

				$x = Libzot::refresh( [ 'hubloc_id_url' => $hub['hubloc_id_url'] ], $r[0], $force );
			}
		}
		else {

			// system wide refresh
			$x = Libzot::refresh( [ 'hubloc_id_url' => $hub['hubloc_id_url'] ], null, $force );
		}

		$ret['success'] = true;
		return $ret;
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

		if (! $sender) {
			return $ret;
		}

		if ($recipients) {
			// basically this means "unfriend"
			foreach ($recipients as $recip) {
				$channel = q("select channel.*,xchan.* from channel
					left join xchan on channel_hash = xchan_hash
					where channel_hash = '%s' limit 1",
					dbesc($recip)
				);
				if ($channel) {
					$abook = q("select abook_id from abook where abook_channel = %d and abook_xchan = '%s' limit 1",
						intval($channel[0]['channel_id']),
						dbesc($sender)
					);
					if ($abook) {
						contact_remove($channel[0]['channel_id'],$abook[0]['abook_id']);
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
