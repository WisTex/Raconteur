<?php

namespace Zotlabs\Lib;

/**
 * @brief implementation of zot6 protocol.
 *
 */


use Zotlabs\Lib\Libsync;

require_once('include/crypto.php');
require_once('include/queue_fn.php');


class Libzot {

	/**
	 * @brief Generates a unique string for use as a zot guid.
	 *
	 * Generates a unique string for use as a zot guid using our DNS-based url, the
	 * channel nickname and some entropy.
	 * The entropy ensures uniqueness against re-installs where the same URL and
	 * nickname are chosen.
	 *
	 * @note zot doesn't require this to be unique. Internally we use a whirlpool
	 * hash of this guid and the signature of this guid signed with the channel
	 * private key. This can be verified and should make the probability of
	 * collision of the verified result negligible within the constraints of our
	 * immediate universe.
	 *
	 * @param string $channel_nick a unique nickname of controlling entity
	 * @returns string
	 */

	static function new_uid($channel_nick) {
		$rawstr = z_root() . '/' . $channel_nick . '.' . mt_rand();
		return(base64url_encode(hash('whirlpool', $rawstr, true), true));
	}


	/**
	 * @brief Generates a portable hash identifier for a channel.
	 *
	 * Generates a portable hash identifier for the channel identified by $guid and
	 * $pubkey.
	 *
	 * @note This ID is portable across the network but MUST be calculated locally
	 * by verifying the signature and can not be trusted as an identity.
	 *
	 * @param string $guid
	 * @param string $pubkey
	 */

	static function make_xchan_hash($guid, $pubkey) {
		return base64url_encode(hash('whirlpool', $guid . $pubkey, true));
	}

	/**
	 * @brief Given a zot hash, return all distinct hubs.
	 *
	 * This function is used in building the zot discovery packet and therefore
	 * should only be used by channels which are defined on this hub.
	 *
	 * @param string $hash - xchan_hash
	 * @returns array of hubloc (hub location structures)
	 *
	 */

	static function get_hublocs($hash) {

		/* Only search for active hublocs - e.g. those that haven't been marked deleted */

		$ret = q("select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 order by hubloc_url ",
			dbesc($hash)
		);

		return $ret;
	}

	/**
	 * @brief Builds a zot6 notification packet.
	 *
	 * Builds a zot6 notification packet that you can either store in the queue with
	 * a message array or call zot_zot to immediately zot it to the other side.
	 *
	 * @param array $channel
	 *   sender channel structure
	 * @param string $type
	 *   packet type: one of 'ping', 'pickup', 'purge', 'refresh', 'keychange', 'force_refresh', 'notify', 'auth_check'
	 * @param array $recipients
	 *   envelope information, array ( 'guid' => string, 'guid_sig' => string ); empty for public posts
	 * @param string msg
	 *   optional message
	 * @param string $remote_key
	 *   optional public site key of target hub used to encrypt entire packet
	 *   NOTE: remote_key and encrypted packets are required for 'auth_check' packets, optional for all others
	 * @param string $methods
	 *   optional comma separated list of encryption methods @ref self::best_algorithm()
	 * @param string $secret
	 *   random string, required for packets which require verification/callback
	 *   e.g. 'pickup', 'purge', 'notify', 'auth_check'. Packet types 'ping', 'force_refresh', and 'refresh' do not require verification
	 * @param string $extra
	 * @returns string json encoded zot packet
	 */

	static function build_packet($channel, $type = 'notify', $recipients = null, $msg = '', $remote_key = null, $methods = '', $extra = null) {

		$sig_method = get_config('system','signature_algorithm','sha256');

		$data = [
			'type' => $type,
			'sender' => [
				'id'            => $channel['channel_guid'],
				'id_sig'        => self::sign($channel['channel_guid'],$channel['channel_prvkey'],$sig_method),
				'id_url'        => z_root() . '/channel/' . $channel['channel_address'],
				'location'      => z_root(),
				'location_sig'  => self::sign(z_root(),$channel['channel_prvkey'],$sig_method),
				'site_id'       => self::make_xchan_hash(z_root(), get_config('system','pubkey')),
			],
			'callback'   => '/zot',
			'version'    => \Zotlabs\Lib\System::get_zot_revision(),
			'encryption' => crypto_methods(),
			'signing'    => signing_methods()
		];

		if ($recipients) {
			$data['recipients'] = $recipients;
		}

		if($msg) {
			$data['data'] = $msg;
		}

		if ($extra) {
			foreach ($extra as $k => $v)
				$data[$k] = $v;
		}

		logger('packet: ' . print_r($data,true), LOGGER_DATA, LOG_DEBUG);

		if($remote_key) {
			$algorithm = self::best_algorithm($methods);
			if($algorithm) {
				$data = crypto_encapsulate(json_encode($data),$remote_key, $algorithm);
			}
		}

		return json_encode($data);
	}




	/**
	 * @brief Choose best encryption function from those available on both sites.
	 *
	 * @param string $methods
	 *   comma separated list of encryption methods
	 * @return string first match from our site method preferences crypto_methods() array
	 * of a method which is common to both sites; or 'aes256cbc' if no matches are found.
	 */

	static function best_algorithm($methods) {

		$x = [
				'methods' => $methods,
				'result' => ''
		];

		/**
		 * @hooks zot_best_algorithm
		 *   Called when negotiating crypto algorithms with remote sites.
		 *   * \e string \b methods - comma separated list of encryption methods
		 *   * \e string \b result - the algorithm to return
		 */
	
		call_hooks('zot_best_algorithm', $x);

		if($x['result'])
			return $x['result'];

		if($methods) {
			$x = explode(',', $methods);
			if($x) {
				$y = crypto_methods();
				if($y) {
					foreach($y as $yv) {
						$yv = trim($yv);
						if(in_array($yv, $x)) {
							return($yv);
						}
					}
				}
			}
		}

		return '';
	}


	/**
	 * @brief send a zot message
	 *
	 * @see z_post_url()
	 *
	 * @param string $url
	 * @param array $data
	 * @param array $channel (required if using zot6 delivery)
	 * @param array $crypto (required if encrypted httpsig, requires hubloc_sitekey and site_crypto elements)
	 * @return array see z_post_url() for returned data format
	 */

	static function zot($url, $data, $channel = null,$crypto = null) {

		$headers = [];

		if($channel) {
			$headers['X-Zot-Token'] = random_string();
			$hash = \Zotlabs\Web\HTTPSig::generate_digest($data,false);
			$headers['Digest'] = 'SHA-256=' . $hash;
			$headers['Content-type'] = 'application/x-zot+json';

			$h = \Zotlabs\Web\HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],channel_url($channel),false,false,'sha512',(($crypto) ? $crypto['hubloc_sitekey'] : ''), (($crypto) ? self::best_algorithm($crypto['site_crypto']) : ''));
		}

		$redirects = 0;
		return z_post_url($url,$data,$redirects,((empty($h)) ? [] : [ 'headers' => $h ]));
	}


	/**
	 * @brief Refreshes after permission changed or friending, etc.
	 *
	 *
	 * refresh is typically invoked when somebody has changed permissions of a channel and they are notified
	 * to fetch new permissions via a finger/discovery operation. This may result in a new connection
	 * (abook entry) being added to a local channel and it may result in auto-permissions being granted.
	 *
	 * Friending in zot is accomplished by sending a refresh packet to a specific channel which indicates a
	 * permission change has been made by the sender which affects the target channel. The hub controlling
	 * the target channel does targetted discovery (a zot-finger request requesting permissions for the local
	 * channel). These are decoded here, and if necessary and abook structure (addressbook) is created to store
	 * the permissions assigned to this channel.
	 *
	 * Initially these abook structures are created with a 'pending' flag, so that no reverse permissions are
	 * implied until this is approved by the owner channel. A channel can also auto-populate permissions in
	 * return and send back a refresh packet of its own. This is used by forum and group communication channels
	 * so that friending and membership in the channel's "club" is automatic.
	 *
	 * @param array $them => xchan structure of sender
	 * @param array $channel => local channel structure of target recipient, required for "friending" operations
	 * @param array $force (optional) default false
	 *
	 * @return boolean
	 *   * \b true if successful
	 *   * otherwise \b false
	 */

	static function refresh($them, $channel = null, $force = false) {

//		if (array_key_exists('xchan_network', $them) && ($them['xchan_network'] !== 'zot6')) {
//			logger('not got zot. ' . $them['xchan_name']);
//			return true;
//		}

		logger('them: ' . print_r($them,true), LOGGER_DATA, LOG_DEBUG);
		if ($channel)
			logger('channel: ' . print_r($channel,true), LOGGER_DATA, LOG_DEBUG);

		$url = null;

		if ($them['hubloc_id_url']) {
			$url = $them['hubloc_id_url'];
		}
		else {
			$r = null;
	
			// if they re-installed the server we could end up with the wrong record - pointing to the old install.
			// We'll order by reverse id to try and pick off the newest one first and hopefully end up with the
			// correct hubloc. If this doesn't work we may have to re-write this section to try them all.

			if(array_key_exists('xchan_addr',$them) && $them['xchan_addr']) {
				$r = q("select hubloc_id_url, hubloc_primary from hubloc where hubloc_addr = '%s' order by hubloc_id desc",
					dbesc($them['xchan_addr'])
				);
			}
			if(! $r) {
				$r = q("select hubloc_id_url, hubloc_primary from hubloc where hubloc_hash = '%s' order by hubloc_id desc",
					dbesc($them['xchan_hash'])
				);
			}

			if ($r) {
				foreach ($r as $rr) {
					if (intval($rr['hubloc_primary'])) {
						$url = $rr['hubloc_id_url'];
						$record = $rr;
					}
				}
				if (! $url) {
					$url = $r[0]['hubloc_id_url'];
				}
			}
		}
		if (! $url) {
			logger('zot_refresh: no url');
			return false;
		}

		$s = q("select site_dead from site where site_url = '%s' limit 1",
			dbesc($url)
		);

		if($s && intval($s[0]['site_dead']) && (! $force)) {
			logger('zot_refresh: site ' . $url . ' is marked dead and force flag is not set. Cancelling operation.');
			return false;
		}

		$record = \Zotlabs\Lib\Zotfinger::exec($url,$channel);

		// Check the HTTP signature

		$hsig = $record['signature'];
		if($hsig && $hsig['signer'] === $url && $hsig['header_valid'] === true && $hsig['content_valid'] === true)
			$hsig_valid = true;

		if(! $hsig_valid) {
			logger('http signature not valid: ' . print_r($hsig,true));
			return $result;
		}


		logger('zot-info: ' . print_r($record,true), LOGGER_DATA, LOG_DEBUG);

		$x = self::import_xchan($record['data'], (($force) ? UPDATE_FLAGS_FORCED : UPDATE_FLAGS_UPDATED));

		if(! $x['success'])
			return false;

		if($channel && $record['data']['permissions']) {
			$old_read_stream_perm = their_perms_contains($channel['channel_id'],$x['hash'],'view_stream');
			set_abconfig($channel['channel_id'],$x['hash'],'system','their_perms',$record['data']['permissions']);

			if(array_key_exists('profile',$record['data']) && array_key_exists('next_birthday',$record['data']['profile'])) {
				$next_birthday = datetime_convert('UTC','UTC',$record['data']['profile']['next_birthday']);
			}
			else {
				$next_birthday = NULL_DATE;
			}

			$profile_assign = get_pconfig($channel['channel_id'],'system','profile_assign','');

			// Keep original perms to check if we need to notify them
			$previous_perms = get_all_perms($channel['channel_id'],$x['hash']);

			$r = q("select * from abook where abook_xchan = '%s' and abook_channel = %d and abook_self = 0 limit 1",
				dbesc($x['hash']),
				intval($channel['channel_id'])
			);

			if($r) {

				// connection exists

				// if the dob is the same as what we have stored (disregarding the year), keep the one
				// we have as we may have updated the year after sending a notification; and resetting
				// to the one we just received would cause us to create duplicated events.

				if(substr($r[0]['abook_dob'],5) == substr($next_birthday,5))
					$next_birthday = $r[0]['abook_dob'];

				$y = q("update abook set abook_dob = '%s'
					where abook_xchan = '%s' and abook_channel = %d
					and abook_self = 0 ",
					dbescdate($next_birthday),
					dbesc($x['hash']),
					intval($channel['channel_id'])
				);

				if(! $y)
					logger('abook update failed');
				else {
					// if we were just granted read stream permission and didn't have it before, try to pull in some posts
					if((! $old_read_stream_perm) && (intval($permissions['view_stream'])))
						\Zotlabs\Daemon\Master::Summon(array('Onepoll',$r[0]['abook_id']));
				}
			}
			else {

				$p = \Zotlabs\Access\Permissions::connect_perms($channel['channel_id']);
				$my_perms = \Zotlabs\Access\Permissions::serialise($p['perms']);

				$automatic = $p['automatic'];

				// new connection

				if($my_perms) {
					set_abconfig($channel['channel_id'],$x['hash'],'system','my_perms',$my_perms);
				}

				$closeness = get_pconfig($channel['channel_id'],'system','new_abook_closeness');
				if($closeness === false)
					$closeness = 80;

				$y = abook_store_lowlevel(
					[
						'abook_account'   => intval($channel['channel_account_id']),
						'abook_channel'   => intval($channel['channel_id']),
						'abook_closeness' => intval($closeness),
						'abook_xchan'     => $x['hash'],
						'abook_profile'   => $profile_assign,
						'abook_created'   => datetime_convert(),
						'abook_updated'   => datetime_convert(),
						'abook_dob'       => $next_birthday,
						'abook_pending'   => intval(($automatic) ? 0 : 1)
					]
				);

				if($y) {
					logger("New introduction received for {$channel['channel_name']}");
					$new_perms = get_all_perms($channel['channel_id'],$x['hash']);
	
					// Send a clone sync packet and a permissions update if permissions have changed

					$new_connection = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d and abook_self = 0 order by abook_created desc limit 1",
						dbesc($x['hash']),
						intval($channel['channel_id'])
					);

					if($new_connection) {
						if(! \Zotlabs\Access\Permissions::PermsCompare($new_perms,$previous_perms))
							\Zotlabs\Daemon\Master::Summon(array('Notifier','permission_create',$new_connection[0]['abook_id']));
						\Zotlabs\Lib\Enotify::submit(
							[
							'type'       => NOTIFY_INTRO,
							'from_xchan' => $x['hash'],
							'to_xchan'   => $channel['channel_hash'],
							'link'       => z_root() . '/connedit/' . $new_connection[0]['abook_id']
							]
						);

						if(intval($permissions['view_stream'])) {
							if(intval(get_pconfig($channel['channel_id'],'perm_limits','send_stream') & PERMS_PENDING)
								|| (! intval($new_connection[0]['abook_pending'])))
								\Zotlabs\Daemon\Master::Summon(array('Onepoll',$new_connection[0]['abook_id']));
						}


						// If there is a default group for this channel, add this connection to it
						// for pending connections this will happens at acceptance time.

						if(! intval($new_connection[0]['abook_pending'])) {
							$default_group = $channel['channel_default_group'];
							if($default_group) {
								require_once('include/group.php');
								$g = group_rec_byhash($channel['channel_id'],$default_group);
								if($g)
									group_add_member($channel['channel_id'],'',$x['hash'],$g['id']);
							}
						}

						unset($new_connection[0]['abook_id']);
						unset($new_connection[0]['abook_account']);
						unset($new_connection[0]['abook_channel']);
						$abconfig = load_abconfig($channel['channel_id'],$new_connection['abook_xchan']);
						if($abconfig)
							$new_connection['abconfig'] = $abconfig;

						self::build_sync_packet($channel['channel_id'], array('abook' => $new_connection));
					}
				}

			}
			return true;

		}
	

		return false;
	}

	/**
	 * @brief Look up if channel is known and previously verified.
	 *
	 * A guid and a url, both signed by the sender, distinguish a known sender at a
	 * known location.
	 * This function looks these up to see if the channel is known and therefore
	 * previously verified. If not, we will need to verify it.
	 *
	 * @param array $arr an associative array which must contain:
	 *  * \e string \b id => id of conversant
	 *  * \e string \b id_sig => id signed with conversant's private key
	 *  * \e string \b location => URL of the origination hub of this communication
	 *  * \e string \b location_sig => URL signed with conversant's private key
	 * @param boolean $multiple (optional) default false
	 *
	 * @return array|null
	 *   * null if site is blacklisted or not found
	 *   * otherwise an array with an hubloc record
	 */

	static function gethub($arr, $multiple = false) {

		if($arr['id'] && $arr['id_sig'] && $arr['location'] && $arr['location_sig']) {

			if(! check_siteallowed($arr['location'])) {
				logger('blacklisted site: ' . $arr['location']);
				return null;
			}

			$limit = (($multiple) ? '' : ' limit 1 ');

			$r = q("select hubloc.*, site.site_crypto from hubloc left join site on hubloc_url = site_url
					where hubloc_guid = '%s' and hubloc_guid_sig = '%s'
					and hubloc_url = '%s' and hubloc_url_sig = '%s'
					and hubloc_site_id = '%s' $limit",
				dbesc($arr['id']),
				dbesc($arr['id_sig']),
				dbesc($arr['location']),
				dbesc($arr['location_sig']),
				dbesc($arr['site_id'])
			);
			if($r) {
				logger('Found', LOGGER_DEBUG);
				return (($multiple) ? $r : $r[0]);
			}
		}
		logger('Not found: ' . print_r($arr,true), LOGGER_DEBUG);

		return false;
	}

	/**
	 * @brief Registers an unknown hub.
	 *
	 * A communication has been received which has an unknown (to us) sender.
	 * Perform discovery based on our calculated hash of the sender at the
	 * origination address. This will fetch the discovery packet of the sender,
	 * which contains the public key we need to verify our guid and url signatures.
	 *
	 * @param array $arr an associative array which must contain:
	 *  * \e string \b guid => guid of conversant
	 *  * \e string \b guid_sig => guid signed with conversant's private key
	 *  * \e string \b url => URL of the origination hub of this communication
	 *  * \e string \b url_sig => URL signed with conversant's private key
	 *
	 * @return array An associative array with
	 *  * \b success boolean true or false
	 *  * \b message (optional) error string only if success is false
	 */

	static function register_hub($arr) {

		$id_hash = false;
		$valid   = false;
		$hsig_valid = false;

		$result  = [ 'success' => false ];

		if($arr['id'] && $arr['id_sig'] && $arr['id_url'] && $arr['location'] && $arr['location_sig'] && $arr['site_id']) {
			$record = \Zotlabs\Lib\Zotfinger::exec($arr['id_url']);

			// Check the HTTP signature

			$hsig = $record['signature'];
			if($hsig['signer'] === $arr['id_url'] && $hsig['header_valid'] === true && $hsig['content_valid'] === true)
				$hsig_valid = true;

			if(! $hsig_valid) {
				logger('http signature not valid: ' . print_r($hsig,true));
				return $result;
			}


			/*
			 * We now have a key - only continue registration if our signatures are valid
			 * AND the guid and guid sig in the returned packet match those provided in
			 * our current communication.
			 */

			if((self::verify($arr['id'],$arr['id_sig'],$record['data']['public_key']))
				&& (zot_verify($arr['location'],$arr['location_sig'],$record['data']['public_key']))
				&& ($arr['id'] === $record['data']['id'])
				&& ($arr['id_sig'] === $record['data']['id_sig'])) {
					$c = import_xchan($record['data']);
					if($c['success'])
						$result['success'] = true;
				}
				else {
					logger('Failure to verify zot packet');
				}
			}

		return $result;
	}

	/**
	 * @brief Takes an associative array of a fetched discovery packet and updates
	 *   all internal data structures which need to be updated as a result.
	 *
	 * @param array $arr => json_decoded discovery packet
	 * @param int $ud_flags
	 *    Determines whether to create a directory update record if any changes occur, default is UPDATE_FLAGS_UPDATED
	 *    $ud_flags = UPDATE_FLAGS_FORCED indicates a forced refresh where we unconditionally create a directory update record
	 *      this typically occurs once a month for each channel as part of a scheduled ping to notify the directory
	 *      that the channel still exists
	 * @param array $ud_arr
	 *    If set [typically by update_directory_entry()] indicates a specific update table row and more particularly
	 *    contains a particular address (ud_addr) which needs to be updated in that table.
	 *
	 * @return array An associative array with:
	 *   * \e boolean \b success boolean true or false
	 *   * \e string \b message (optional) error string only if success is false
	 */

	static function import_xchan($arr, $ud_flags = UPDATE_FLAGS_UPDATED, $ud_arr = null) {

		/**
		 * @hooks import_xchan
		 *   Called when processing the result of zot_finger() to store the result
		 *   * \e array
		 */
		call_hooks('import_xchan', $arr);

		$ret = array('success' => false);
		$dirmode = intval(get_config('system','directory_mode'));

		$changed = false;
		$what = '';

		if(! ($arr['id'] && $arr['id_sig'])) {
			logger('No identity information provided. ' . print_r($arr,true));
			return $ret;
		}

		$xchan_hash = self::make_xchan_hash($arr['id'],$arr['public_key']);
		$arr['hash'] = $xchan_hash;

		$import_photos = false;

		$sig_methods = ((array_key_exists('signing',$arr) && is_array($arr['signing'])) ? $arr['signing'] : [ 'sha256' ]);
		$verified = false;

		if(! self::verify($arr['id'],$arr['id_sig'],$arr['public_key'])) {
			logger('Unable to verify channel signature for ' . $arr['address']);
			return $ret;
		}
		else {
			$verified = true;
		}

		if(! $verified) {
			$ret['message'] = t('Unable to verify channel signature');
			return $ret;
		}

		logger('import_xchan: ' . $xchan_hash, LOGGER_DEBUG);

		$r = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($xchan_hash)
		);

		if(! array_key_exists('connect_url', $arr))
			$arr['connect_url'] = '';

		if($r) {
			if($arr['photo'] && array_key_exists('updated',$arr['photo']) && $r[0]['xchan_photo_date'] != $arr['photo']['updated']) {
				$import_photos = true;
			}

			// if we import an entry from a site that's not ours and either or both of us is off the grid - hide the entry.
			/** @TODO: check if we're the same directory realm, which would mean we are allowed to see it */

			$dirmode = get_config('system','directory_mode');

			if((($arr['site']['directory_mode'] === 'standalone') || ($dirmode & DIRECTORY_MODE_STANDALONE)) && ($arr['site']['url'] != z_root()))
				$arr['searchable'] = false;

			$hidden = (1 - intval($arr['searchable']));

			$hidden_changed = $adult_changed = $deleted_changed = $pubforum_changed = 0;

			if(intval($r[0]['xchan_hidden']) != (1 - intval($arr['searchable'])))
				$hidden_changed = 1;
			if(intval($r[0]['xchan_selfcensored']) != intval($arr['adult_content']))
				$adult_changed = 1;
			if(intval($r[0]['xchan_deleted']) != intval($arr['deleted']))
				$deleted_changed = 1;
			if(intval($r[0]['xchan_pubforum']) != intval($arr['public_forum']))
				$pubforum_changed = 1;

			if($arr['protocols']) {
				$protocols = implode(',',$arr['protocols']);
				if($protocols !== 'zot') {
					set_xconfig($xchan_hash,'system','protocols',$protocols);
				}
				else {
					del_xconfig($xchan_hash,'system','protocols');
				}
			}

			if(($r[0]['xchan_name_date'] != $arr['name_updated'])
				|| ($r[0]['xchan_connurl'] != $arr['connections_url'])
				|| ($r[0]['xchan_addr'] != $arr['address'])
				|| ($r[0]['xchan_follow'] != $arr['follow_url'])
				|| ($r[0]['xchan_connpage'] != $arr['connect_url'])
				|| ($r[0]['xchan_url'] != $arr['url'])
				|| $hidden_changed || $adult_changed || $deleted_changed || $pubforum_changed ) {
				$rup = q("update xchan set xchan_name = '%s', xchan_name_date = '%s', xchan_connurl = '%s', xchan_follow = '%s',
					xchan_connpage = '%s', xchan_hidden = %d, xchan_selfcensored = %d, xchan_deleted = %d, xchan_pubforum = %d,
					xchan_addr = '%s', xchan_url = '%s' where xchan_hash = '%s'",
					dbesc(($arr['name']) ? $arr['name'] : '-'),
					dbesc($arr['name_updated']),
					dbesc($arr['connections_url']),
					dbesc($arr['follow_url']),
					dbesc($arr['connect_url']),
					intval(1 - intval($arr['searchable'])),
					intval($arr['adult_content']),
					intval($arr['deleted']),
					intval($arr['public_forum']),
					dbesc($arr['address']),
					dbesc($arr['url']),
					dbesc($xchan_hash)
				);

				logger('Update: existing: ' . print_r($r[0],true), LOGGER_DATA, LOG_DEBUG);
				logger('Update: new: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);
				$what .= 'xchan ';
				$changed = true;
			}
		}
		else {
			$import_photos = true;

			if((($arr['site']['directory_mode'] === 'standalone')
					|| ($dirmode & DIRECTORY_MODE_STANDALONE))
					&& ($arr['site']['url'] != z_root()))
				$arr['searchable'] = false;

			$x = xchan_store_lowlevel(
				[
					'xchan_hash'           => $xchan_hash,
					'xchan_guid'           => $arr['id'],
					'xchan_guid_sig'       => $arr['id_sig'],
					'xchan_pubkey'         => $arr['key'],
					'xchan_photo_mimetype' => $arr['photo_mimetype'],
					'xchan_photo_l'        => $arr['photo'],
					'xchan_addr'           => $arr['address'],
					'xchan_url'            => $arr['url'],
					'xchan_connurl'        => $arr['connections_url'],
					'xchan_follow'         => $arr['follow_url'],
					'xchan_connpage'       => $arr['connect_url'],
					'xchan_name'           => (($arr['name']) ? $arr['name'] : '-'),
					'xchan_network'        => 'zot6',
					'xchan_photo_date'     => $arr['photo_updated'],
					'xchan_name_date'      => $arr['name_updated'],
					'xchan_hidden'         => intval(1 - intval($arr['searchable'])),
					'xchan_selfcensored'   => $arr['adult_content'],
					'xchan_deleted'        => $arr['deleted'],
					'xchan_pubforum'       => $arr['public_forum']
				]
			);

			$what .= 'new_xchan';
			$changed = true;
		}

		if($import_photos) {

			require_once('include/photo/photo_driver.php');

			// see if this is a channel clone that's hosted locally - which we treat different from other xchans/connections

			$local = q("select channel_account_id, channel_id from channel where channel_hash = '%s' limit 1",
				dbesc($xchan_hash)
			);
			if($local) {
				$ph = z_fetch_url($arr['photo']['url'], true);
				if($ph['success']) {

					$hash = import_channel_photo($ph['body'], $arr['photo']['type'], $local[0]['channel_account_id'], $local[0]['channel_id']);

					if($hash) {
						// unless proven otherwise
						$is_default_profile = 1;

						$profile = q("select is_default from profile where aid = %d and uid = %d limit 1",
							intval($local[0]['channel_account_id']),
							intval($local[0]['channel_id'])
						);
						if($profile) {
							if(! intval($profile[0]['is_default']))
								$is_default_profile = 0;
						}

						// If setting for the default profile, unset the profile photo flag from any other photos I own
						if($is_default_profile) {
							q("UPDATE photo SET photo_usage = %d WHERE photo_usage = %d AND resource_id != '%s' AND aid = %d AND uid = %d",	
								intval(PHOTO_NORMAL),
								intval(PHOTO_PROFILE),
								dbesc($hash),
								intval($local[0]['channel_account_id']),
								intval($local[0]['channel_id'])
							);
						}
					}

					// reset the names in case they got messed up when we had a bug in this function
					$photos = array(
						z_root() . '/photo/profile/l/' . $local[0]['channel_id'],
						z_root() . '/photo/profile/m/' . $local[0]['channel_id'],
						z_root() . '/photo/profile/s/' . $local[0]['channel_id'],
						$arr['photo_mimetype'],
						false
					);
				}
			}
			else {
				$photos = import_xchan_photo($arr['photo']['url'], $xchan_hash);
			}
			if($photos) {
				if($photos[4]) {
					// importing the photo failed somehow. Leave the photo_date alone so we can try again at a later date.
					// This often happens when somebody joins the matrix with a bad cert.
					$r = q("update xchan set xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s'
						where xchan_hash = '%s'",
						dbesc($photos[0]),
						dbesc($photos[1]),
						dbesc($photos[2]),
						dbesc($photos[3]),
						dbesc($xchan_hash)
					);
				}
				else {
					$r = q("update xchan set xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s'
						where xchan_hash = '%s'",
						dbescdate(datetime_convert('UTC','UTC',$arr['photo_updated'])),
						dbesc($photos[0]),
						dbesc($photos[1]),
						dbesc($photos[2]),
						dbesc($photos[3]),
						dbesc($xchan_hash)
					);
				}
				$what .= 'photo ';
				$changed = true;
			}
		}

		// what we are missing for true hub independence is for any changes in the primary hub to
		// get reflected not only in the hublocs, but also to update the URLs and addr in the appropriate xchan

		$s = self::sync_locations($arr, $arr);

		if($s) {
			if($s['change_message'])
				$what .= $s['change_message'];
			if($s['changed'])
				$changed = $s['changed'];
			if($s['message'])
				$ret['message'] .= $s['message'];
		}

		// Which entries in the update table are we interested in updating?

		$address = (($ud_arr && $ud_arr['ud_addr']) ? $ud_arr['ud_addr'] : $arr['address']);


		// Are we a directory server of some kind?

		$other_realm = false;
		$realm = get_directory_realm();
		if(array_key_exists('site',$arr)
			&& array_key_exists('realm',$arr['site'])
			&& (strpos($arr['site']['realm'],$realm) === false))
			$other_realm = true;

		if($dirmode != DIRECTORY_MODE_NORMAL) {

			// We're some kind of directory server. However we can only add directory information
			// if the entry is in the same realm (or is a sub-realm). Sub-realms are denoted by
			// including the parent realm in the name. e.g. 'RED_GLOBAL:foo' would allow an entry to
			// be in directories for the local realm (foo) and also the RED_GLOBAL realm.

			if(array_key_exists('profile',$arr) && is_array($arr['profile']) && (! $other_realm)) {
				$profile_changed = import_directory_profile($xchan_hash,$arr['profile'],$address,$ud_flags, 1);
				if($profile_changed) {
					$what .= 'profile ';
					$changed = true;
				}
			}
			else {
				logger('Profile not available - hiding');
				// they may have made it private
				$r = q("delete from xprof where xprof_hash = '%s'",
					dbesc($xchan_hash)
				);
				$r = q("delete from xtag where xtag_hash = '%s' and xtag_flags = 0",
					dbesc($xchan_hash)
				);
			}
		}

		if(array_key_exists('site',$arr) && is_array($arr['site'])) {
			$profile_changed = self::import_site($arr['site'],$arr['key']);
			if($profile_changed) {
				$what .= 'site ';
				$changed = true;
			}
		}

		if(($changed) || ($ud_flags == UPDATE_FLAGS_FORCED)) {
			$guid = random_string() . '@' . App::get_hostname();
			update_modtime($xchan_hash,$guid,$address,$ud_flags);
			logger('Changed: ' . $what,LOGGER_DEBUG);
		}
		elseif(! $ud_flags) {
			// nothing changed but we still need to update the updates record
			q("update updates set ud_flags = ( ud_flags | %d ) where ud_addr = '%s' and not (ud_flags & %d) > 0 ",
				intval(UPDATE_FLAGS_UPDATED),
				dbesc($address),
				intval(UPDATE_FLAGS_UPDATED)
			);
		}

		if(! x($ret,'message')) {
			$ret['success'] = true;
			$ret['hash'] = $xchan_hash;
		}

		logger('Result: ' . print_r($ret,true), LOGGER_DATA, LOG_DEBUG);
		return $ret;
	}

	/**
	 * @brief Called immediately after sending a zot message which is using queue processing.
	 *
	 * Updates the queue item according to the response result and logs any information
	 * returned to aid communications troubleshooting.
	 *
	 * @param string $hub - url of site we just contacted
	 * @param array $arr - output of z_post_url()
	 * @param array $outq - The queue structure attached to this request
	 */

	static function process_response($hub, $arr, $outq) {

		if(! $arr['success']) {
			logger('Failed: ' . $hub);
			return;
		}

		$x = json_decode($arr['body'], true);

		if(! $x) {
			logger('No json from ' . $hub);
			logger('Headers: ' . print_r($arr['header'], true), LOGGER_DATA, LOG_DEBUG);
		}

		if(is_array($x) && array_key_exists('delivery_report',$x) && is_array($x['delivery_report'])) {

			if(array_key_exists('iv',$x['delivery_report'])) {
				$j = crypto_unencapsulate($x['delivery_report'],get_config('system','prvkey'));
				if($j) {
					$x['delivery_report'] = json_decode($j,true);
				}
				if(! (is_array($x['delivery_report']) && count($x['delivery_report']))) {
					logger('encrypted delivery report could not be decrypted');
					return;
				}
			}

			foreach($x['delivery_report'] as $xx) {
				if(is_array($xx) && array_key_exists('message_id',$xx) && self::delivery_report_is_storable($xx)) {
					q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_result, dreport_time, dreport_xchan ) values ( '%s', '%s','%s','%s','%s','%s' ) ",
						dbesc($xx['message_id']),
						dbesc($xx['location']),
						dbesc($xx['recipient']),
						dbesc($xx['status']),
						dbesc(datetime_convert($xx['date'])),
						dbesc($xx['sender'])
					);
				}
			}
		}

		// we have a more descriptive delivery report, so discard the per hub 'queued' report.

		q("delete from dreport where dreport_queue = '%s' ",
			dbesc($outq['outq_hash'])
		);

		// update the timestamp for this site

		q("update site set site_dead = 0, site_update = '%s' where site_url = '%s'",
			dbesc(datetime_convert()),
			dbesc(dirname($hub))
		);

		// synchronous message types are handled immediately
		// async messages remain in the queue until processed.

		if(intval($outq['outq_async']))
			remove_queue_item($outq['outq_hash'],$outq['outq_channel']);

		logger('zot_process_response: ' . print_r($x,true), LOGGER_DEBUG);
	}

	/**
	 * @brief
	 *
	 * We received a notification packet (in mod_post) that a message is waiting for us, and we've verified the sender.
	 * Check if the site is using zot6 delivery and includes a verified HTTP Signature, signed content, and a 'msg' field,
	 * and also that the signer and the sender match.
	 * If that happens, we do not need to fetch/pickup the message - we have it already and it is verified.
	 * Translate it into the form we need for zot_import() and import it.
	 *
	 * Otherwise send back a pickup message, using our message tracking ID ($arr['secret']), which we will sign with our site
	 * private key.
	 * The entire pickup message is encrypted with the remote site's public key.
	 * If everything checks out on the remote end, we will receive back a packet containing one or more messages,
	 * which will be processed and delivered before this function ultimately returns.
	 *
	 * @see zot_import()
	 *
	 * @param array $arr
	 *     decrypted and json decoded notify packet from remote site
	 * @return array from zot_import()
	 */

	static function fetch($arr) {

		logger('zot_fetch: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);

		$url = $arr['sender']['location'] . $arr['callback'];

		$import = null;
		$hubs   = null;


		logger('zot6_delivery',LOGGER_DEBUG);
		logger('zot6_data: ' . print_r($arr,true),LOGGER_DATA);

		$ret['collected'] = true;

		$import = [ 'success' => true, 'body' => json_encode( [ 'success' => true, 'pickup' => [ [ 'notify' => $arr, 'message' => json_decode($arr['data'],true) ] ] ] ) ];

		if(! $hubs) {
			// set $multiple param on zot_gethub() to return all matching hubs
			// This allows us to recover from re-installs when a redundant (but invalid) hubloc for
			// this identity is widely dispersed throughout the network.

			$hubs = self::gethub($arr['sender'],true);
		}

		if(! $hubs) {
			logger('No hub: ' . print_r($arr['sender'],true));
			return;
		}

		foreach($hubs as $hub) {

			$algorithm = self::best_algorithm($hub['site_crypto']);
			$result = self::import($import, $arr['sender']['location']);

			if($result) {
				$result = crypto_encapsulate(json_encode($result),$hub['hubloc_sitekey'], $algorithm);
				return $result;
			}

		}

		return;
	}

	/**
	 * @brief Process incoming array of messages.
	 *
	 * Process an incoming array of messages which were obtained via pickup, and
	 * import, update, delete as directed.
	 *
	 * The message types handled here are 'activity' (e.g. posts), 'mail',
	 * 'profile', 'location' and 'channel_sync'.
	 *
	 * @param array $arr
	 *  'pickup' structure returned from remote site
	 * @param string $sender_url
	 *  the url specified by the sender in the initial communication.
	 *  We will verify the sender and url in each returned message structure and
	 *  also verify that all the messages returned match the site url that we are
	 *  currently processing.
	 *
	 * @returns array
	 *   Suitable for logging remotely, enumerating the processing results of each message/recipient combination
	 *   * [0] => \e string $channel_hash
	 *   * [1] => \e string $delivery_status
	 *   * [2] => \e string $address
	 */

	static function import($arr, $sender_url) {

		$data = json_decode($arr['body'], true);

		if(! $data) {
			logger('Empty body');
			return [];
		}

		if(array_key_exists('encrypted', $data) && intval($data['encrypted'])) {
			$data = json_decode(crypto_unencapsulate($data,get_config('system','prvkey')),true);
		}

		if(! is_array($data)) {
			logger('decode error');
			return [];
		}

		if(! $data['success']) {
			if($data['message'])
				logger('remote pickup failed: ' . $data['message']);
			return false;
		}

		$incoming = $data['pickup'];

		$return = [];

		if(is_array($incoming)) {
			foreach($incoming as $i) {
				if(! is_array($i)) {
					logger('incoming is not an array');
					continue;
				}

				$result = null;

				if(array_key_exists('encrypted',$i['notify']) && intval($i['notify']['enctrypted'])) {
					$i['notify'] = json_decode(crypto_unencapsulate($i['notify'],get_config('system','prvkey')),true);
				}

				logger('Notify: ' . print_r($i['notify'],true), LOGGER_DATA, LOG_DEBUG);

				if(! is_array($i['notify'])) {
					logger('decode error');
					continue;
				}


				$hub = self::gethub($i['notify']['sender']);
				if((! $hub) || ($hub['hubloc_url'] != $sender_url)) {
					logger('Potential forgery: wrong site for sender: ' . $sender_url . ' != ' . print_r($i['notify'],true));
					continue;
				}

				$message_request = ((array_key_exists('message_id',$i['notify'])) ? true : false);
				if($message_request)
					logger('processing message request');

				$i['notify']['sender']['hash'] = $hub['hubloc_hash'];
				$deliveries = null;

				if(array_key_exists('message',$i) && array_key_exists('type',$i['message']) && $i['message']['type'] === 'rating') {
					// rating messages are processed only by directory servers
					logger('Rating received: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);
					$result = process_rating_delivery($i['notify']['sender'],$i['message']);
					continue;
				}

				if(array_key_exists('recipients',$i['notify']) && count($i['notify']['recipients'])) {
					logger('specific recipients');
					$recip_arr = array();
					foreach($i['notify']['recipients'] as $recip) {
						if(is_array($recip)) {
							$recip_arr[] =  $recip['portable_id'];
						}
					}

					$r = false;
					if($recip_arr) {
						stringify_array_elms($recip_arr);
						$recips = implode(',',$recip_arr);
						$r = q("select channel_hash as hash from channel where channel_hash in ( " . $recips . " ) and channel_removed = 0 ");
					}

					if(! $r) {
						logger('recips: no recipients on this site');
						continue;
					}

					// It's a specifically targetted post. If we were sent a public_scope hint (likely),
					// get rid of it so that it doesn't get stored and cause trouble.

					if(($i) && is_array($i) && array_key_exists('message',$i) && is_array($i['message'])
						&& $i['message']['type'] === 'activity' && array_key_exists('public_scope',$i['message']))
						unset($i['message']['public_scope']);

					$deliveries = $r;

					// We found somebody on this site that's in the recipient list.

				}
				else {
					if(($i['message']) && (array_key_exists('flags',$i['message'])) && (in_array('private',$i['message']['flags'])) && $i['message']['type'] === 'activity') {
						if(array_key_exists('public_scope',$i['message']) && $i['message']['public_scope'] === 'public') {
							// This should not happen but until we can stop it...
							logger('private message was delivered with no recipients.');
							continue;
						}
					}

					logger('public post');

					// Public post. look for any site members who are or may be accepting posts from this sender
					// and who are allowed to see them based on the sender's permissions

					$deliveries = self::allowed_public_recips($i);

					if($i['message'] && array_key_exists('type',$i['message']) && $i['message']['type'] === 'location') {
						$sys = get_sys_channel();
						$deliveries = array(array('hash' => $sys['xchan_hash']));
					}

					// if the scope is anything but 'public' we're going to store it as private regardless
					// of the private flag on the post.
	
					if($i['message'] && array_key_exists('public_scope',$i['message'])
						&& $i['message']['public_scope'] !== 'public') {

						if(! array_key_exists('flags',$i['message']))
							$i['message']['flags'] = array();
						if(! in_array('private',$i['message']['flags']))
							$i['message']['flags'][] = 'private';
					}
				}


				// Go through the hash array and remove duplicates. array_unique() won't do this because the array is more than one level.

				$no_dups = array();
				if($deliveries) {
					foreach($deliveries as $d) {
						if(! is_array($d)) {
							logger('Delivery hash array is not an array: ' . print_r($d,true));
							continue;
						}
						if(! in_array($d['hash'],$no_dups))
							$no_dups[] = $d['hash'];
					}

					if($no_dups) {
						$deliveries = array();
						foreach($no_dups as $n) {
							$deliveries[] = array('hash' => $n);
						}
					}
				}

				if(! $deliveries) {
					logger('No deliveries on this site');
					continue;
				}

				if($i['message']) {
					if($i['message']['type'] === 'activity') {

						if($i['message']['encoding'] === 'zot') {
							$arr = get_item_elements($i['message']);
	
							$v = validate_item_elements($i['message'],$arr);
					
							if(! $v['success']) {
								logger('Activity rejected: ' . $v['message'] . ' ' . print_r($i['message'],true));
								continue;
							}
						}
						elseif($i['message']['encoding'] === 'activitystreams') {

							$AS = new \Zotlabs\Lib\ActivityStreams($i['message']['content']);
							if(! $AS->is_valid()) {
								logger('Activity rejected: ' . print_r($i['message'],true));
								continue;
							}
							$arr = \Zotlabs\Lib\Activity::decode_note($AS);
							$r = q("select hubloc_hash from hubloc where hubloc_id_url = '%s' limit 1",
								dbesc($AS->actor['id'])
							); 
							if($r) {
								$arr['author_xchan'] = $r[0]['hubloc_hash'];
							}
							// @fixme (in individual delivery, change owner if needed)
							$arr['owner_xchan'] = $i['notify']['sender']['hash'];						

						}

						logger('Activity received: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);
						logger('Activity recipients: ' . print_r($deliveries,true), LOGGER_DATA, LOG_DEBUG);

						$relay = ((array_key_exists('flags',$i['message']) && in_array('relay',$i['message']['flags'])) ? true : false);
						$result = self::process_delivery($i['notify']['sender'],$arr,$deliveries,$relay,false,$message_request);
					}
					elseif($i['message']['type'] === 'mail') {
						$arr = get_mail_elements($i['message']);

						logger('Mail received: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);
						logger('Mail recipients: ' . print_r($deliveries,true), LOGGER_DATA, LOG_DEBUG);
	
						$result = self::process_mail_delivery($i['notify']['sender'],$arr,$deliveries);
					}
					elseif($i['message']['type'] === 'profile') {
						$arr = get_profile_elements($i['message']);

						logger('Profile received: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);
						logger('Profile recipients: ' . print_r($deliveries,true), LOGGER_DATA, LOG_DEBUG);
	
						$result = self::process_profile_delivery($i['notify']['sender'],$arr,$deliveries);
					}
					elseif($i['message']['type'] === 'channel_sync') {
						// $arr = get_channelsync_elements($i['message']);

						$arr = $i['message'];

						logger('Channel sync received: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);
						logger('Channel sync recipients: ' . print_r($deliveries,true), LOGGER_DATA, LOG_DEBUG);

						$result = Libsync::process_channel_sync_delivery($i['notify']['sender'],$arr,$deliveries);
					}
					elseif($i['message']['type'] === 'location') {
						$arr = $i['message'];

						logger('Location message received: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);
						logger('Location message recipients: ' . print_r($deliveries,true), LOGGER_DATA, LOG_DEBUG);

						$result = self::process_location_delivery($i['notify']['sender'],$arr,$deliveries);
					}
				}
				if ($result) {
					$return = array_merge($return, $result);
				}
			}
		}

		return $return;
	}

	/**
	 * @brief
	 *
	 * A public message with no listed recipients can be delivered to anybody who
	 * has PERMS_NETWORK for that type of post, PERMS_AUTHED (in-network senders are
	 * by definition authenticated) or PERMS_SITE and is one the same site,
	 * or PERMS_SPECIFIC and the sender is a contact who is granted permissions via
	 * their connection permissions in the address book.
	 * Here we take a given message and construct a list of hashes of everybody
	 * on the site that we should try and deliver to.
	 * Some of these will be rejected, but this gives us a place to start.
	 *
	 * @param array $msg
	 * @return NULL|array
	 */

	static function public_recips($msg) {

		require_once('include/channel.php');

		$check_mentions = false;
		$include_sys = false;

		if($msg['message']['type'] === 'activity') {
			$disable_discover_tab = get_config('system','disable_discover_tab') || get_config('system','disable_discover_tab') === false;
			if(! $disable_discover_tab)
				$include_sys = true;

			$perm = 'send_stream';

			if(array_key_exists('flags',$msg['message']) && in_array('thread_parent', $msg['message']['flags'])) {
				// check mention recipient permissions on top level posts only
				$check_mentions = true;
			}
			else {

				// This doesn't look like it works so I have to explain what happened. These are my
				// notes (below) from when I got this section of code working. You would think that
				// we only have to find those with the requisite stream or comment permissions,
				// depending on whether this is a top-level post or a comment - but you would be wrong.

				// ... so public_recips and allowed_public_recips is working so much better
				// than before, but was still not quite right. We seem to be getting all the right
				// results for top-level posts now, but comments aren't getting through on channels
				// for which we've allowed them to send us their stream, but not comment on our posts.
				// The reason is we were seeing if they could comment - and we only need to do that if
				// we own the post. If they own the post, we only need to check if they can send us their stream.

				// if this is a comment and it wasn't sent by the post owner, check to see who is allowing them to comment.
				// We should have one specific recipient and this step shouldn't be needed unless somebody stuffed up
				// their software. We may need this step to protect us from bad guys intentionally stuffing up their software.
				// If it is sent by the post owner, we don't need to do this. We only need to see who is receiving the
				// owner's stream (which was already set above) - as they control the comment permissions, not us.

				// Note that by doing this we introduce another bug because some public forums have channel_w_stream
				// permissions set to themselves only. We also need in this function to add these public forums to the
				// public recipient list based on if they are tagged or not and have tag permissions. This is complicated
				// by the fact that this activity doesn't have the public forum tag. It's the parent activity that
				// contains the tag. we'll solve that further below.

				if($msg['notify']['sender']['id_sig'] != $msg['message']['owner']['id_sig']) {
					$perm = 'post_comments';
				}
			}
		}
		elseif($msg['message']['type'] === 'mail')
			$perm = 'post_mail';

		$r = array();

		$c = q("select channel_id, channel_hash from channel where channel_removed = 0");
		if($c) {
			foreach($c as $cc) {
				if(perm_is_allowed($cc['channel_id'],$msg['notify']['sender']['hash'],$perm)) {
					$r[] = [ 'hash' => $cc['channel_hash'] ];
				}
			}
		}

		// logger('message: ' . print_r($msg['message'],true));

		if($include_sys && array_key_exists('public_scope',$msg['message']) && $msg['message']['public_scope'] === 'public') {
			$sys = get_sys_channel();
			if($sys)
				$r[] = [ 'hash' => $sys['channel_hash'] ];
		}

		// look for any public mentions on this site
		// They will get filtered by tgroup_check() so we don't need to check permissions now

		if($check_mentions) {
			// It's a top level post. Look at the tags. See if any of them are mentions and are on this hub.
			if($msg['message']['tags']) {
				if(is_array($msg['message']['tags']) && $msg['message']['tags']) {
					foreach($msg['message']['tags'] as $tag) {
						if(($tag['type'] === 'mention' || $tag['type'] === 'forum') && (strpos($tag['url'],z_root()) !== false)) {
							$address = basename($tag['url']);
							if($address) {
								$z = q("select channel_hash as hash from channel where channel_address = '%s'
									and channel_removed = 0 limit 1",
									dbesc($address)
								);
								if($z)
									$r = array_merge($r,$z);
							}
						}
					}
				}
			}
		}
		else {
			// This is a comment. We need to find any parent with ITEM_UPLINK set. But in fact, let's just return
			// everybody that stored a copy of the parent. This way we know we're covered. We'll check the
			// comment permissions when we deliver them.

			if($msg['message']['message_top']) {
				$z = q("select owner_xchan as hash from item where parent_mid = '%s' ",
					dbesc($msg['message']['message_top'])
				);
				if($z)
					$r = array_merge($r,$z);
			}
		}

		// There are probably a lot of duplicates in $r at this point. We need to filter those out.
		// It's a bit of work since it's a multi-dimensional array

		if($r) {
			$uniq = array();
	
			foreach($r as $rr) {
				if(! in_array($rr['hash'],$uniq))
					$uniq[] = $rr['hash'];
			}
			$r = array();
			foreach($uniq as $rr) {
				$r[] = array('hash' => $rr);
			}
		}

		logger('public_recips: ' . print_r($r,true), LOGGER_DATA, LOG_DEBUG);
		return $r;
	}

	/**
	 * @brief This is the second part of public_recips().
	 *
	 * We'll find all the channels willing to accept public posts from us, then
	 * match them against the sender privacy scope and see who in that list that
	 * the sender is allowing.
	 *
	 * @see public_recipes()
	 * @param array $msg
	 * @return array
	 */

	static function allowed_public_recips($msg) {

		logger('allowed_public_recips: ' . print_r($msg,true),LOGGER_DATA, LOG_DEBUG);

		if(array_key_exists('public_scope',$msg['message']))
			$scope = $msg['message']['public_scope'];

		// Mail won't have a public scope.
		// in fact, it's doubtful mail will ever get here since it almost universally
		// has a recipient, but in fact we don't require this, so it's technically
		// possible to send mail to anybody that's listening.

		$recips = self::public_recips($msg);

		if(! $recips)
			return $recips;

		if($msg['message']['type'] === 'mail')
			return $recips;

		if($msg['message']['encoding'] !== 'zot')
			return $recips;

		if($scope === 'public' || $scope === 'network: red' || $scope === 'authenticated')
			return $recips;

		if(strpos($scope,'site:') === 0) {
			if(($scope === 'site: ' . App::get_hostname()) && ($msg['notify']['sender']['url'] === z_root()))
				return $recips;
			else
				return array();
		}

//@fixme
		$hash = self::make_xchan_hash($msg['notify']['sender']['guid'],$msg['notify']['sender']['guid_sig']);

		if($scope === 'self') {
			foreach($recips as $r)
				if($r['hash'] === $hash)
					return array('hash' => $hash);
		}

		// note: we shouldn't ever see $scope === 'specific' in this function, but handle it anyway

		if($scope === 'contacts' || $scope === 'any connections' || $scope === 'specific') {
			$condensed_recips = array();
			foreach($recips as $rr)
				$condensed_recips[] = $rr['hash'];

			$results = array();
			$r = q("select channel_hash as hash from channel left join abook on abook_channel = channel_id where abook_xchan = '%s' and channel_removed = 0 ",
				dbesc($hash)
			);
			if($r) {
				foreach($r as $rr)
					if(in_array($rr['hash'],$condensed_recips))
						$results[] = array('hash' => $rr['hash']);
			}
			return $results;
		}

		return array();
	}

	/**
	 * @brief
	 *
	 * @param array $sender
	 * @param array $arr
	 * @param array $deliveries
	 * @param boolean $relay
	 * @param boolean $public (optional) default false
	 * @param boolean $request (optional) default false
	 * @return array
	 */

	static function process_delivery($sender, $arr, $deliveries, $relay, $public = false, $request = false) {

		$result = array();

		$result['site'] = z_root();

		// We've validated the sender. Now make sure that the sender is the owner or author

		if(! $public) {
			if($sender['hash'] != $arr['owner_xchan'] && $sender['hash'] != $arr['author_xchan']) {
				logger("Sender {$sender['hash']} is not owner {$arr['owner_xchan']} or author {$arr['author_xchan']} - mid {$arr['mid']}");
				return;
			}
		}

		foreach($deliveries as $d) {
			$local_public = $public;

			$DR = new \Zotlabs\Lib\DReport(z_root(),$sender['hash'],$d['hash'],$arr['mid']);

			$r = q("select * from channel where channel_hash = '%s' limit 1",
				dbesc($d['hash'])
			);

			if(! $r) {
				$DR->update('recipient not found');
				$result[] = $DR->get();
				continue;
			}

			$channel = $r[0];
			$DR->addto_recipient($channel['channel_name'] . ' <' . channel_reddress($channel) . '>');

			/* blacklisted channels get a permission denied, no special message to tip them off */

			if(! check_channelallowed($sender['hash'])) {
				$DR->update('permission denied');
				$result[] = $DR->get();
				continue;
			}

			/**
			 * @FIXME: Somehow we need to block normal message delivery from our clones, as the delivered
			 * message doesn't have ACL information in it as the cloned copy does. That copy
			 * will normally arrive first via sync delivery, but this isn't guaranteed.
			 * There's a chance the current delivery could take place before the cloned copy arrives
			 * hence the item could have the wrong ACL and *could* be used in subsequent deliveries or
			 * access checks. So far all attempts at identifying this situation precisely
			 * have caused issues with delivery of relayed comments.
			 */

	//		if(($d['hash'] === $sender['hash']) && ($sender['url'] !== z_root()) && (! $relay)) {
	//			$DR->update('self delivery ignored');
	//			$result[] = $DR->get();
	//			continue;
	//		}

			// allow public postings to the sys channel regardless of permissions, but not
			// for comments travelling upstream. Wait and catch them on the way down.
			// They may have been blocked by the owner.

			if(intval($channel['channel_system']) && (! $arr['item_private']) && (! $relay)) {
				$local_public = true;

				$r = q("select xchan_selfcensored from xchan where xchan_hash = '%s' limit 1",
					dbesc($sender['hash'])
				);
				// don't import sys channel posts from selfcensored authors
				if($r && (intval($r[0]['xchan_selfcensored']))) {
					$local_public = false;
					continue;
				}
				if(! \Zotlabs\Lib\MessageFilter::evaluate($arr,get_config('system','pubstream_incl'),get_config('system','pubstream_excl'))) {
					$local_public = false;
					continue;
				}
			}

			$tag_delivery = tgroup_check($channel['channel_id'],$arr);

			$perm = 'send_stream';
			if(($arr['mid'] !== $arr['parent_mid']) && ($relay))
				$perm = 'post_comments';

			// This is our own post, possibly coming from a channel clone

			if($arr['owner_xchan'] == $d['hash']) {
				$arr['item_wall'] = 1;
			}
			else {
				$arr['item_wall'] = 0;
			}

			if((! perm_is_allowed($channel['channel_id'],$sender['hash'],$perm)) && (! $tag_delivery) && (! $local_public)) {
				logger("permission denied for delivery to channel {$channel['channel_id']} {$channel['channel_address']}");
				$DR->update('permission denied');
				$result[] = $DR->get();
				continue;
			}

			if($arr['mid'] != $arr['parent_mid']) {

				// check source route.
				// We are only going to accept comments from this sender if the comment has the same route as the top-level-post,
				// this is so that permissions mismatches between senders apply to the entire conversation
				// As a side effect we will also do a preliminary check that we have the top-level-post, otherwise
				// processing it is pointless.
	
				$r = q("select route, id from item where mid = '%s' and uid = %d limit 1",
					dbesc($arr['parent_mid']),
					intval($channel['channel_id'])
				);
				if(! $r) {
					$DR->update('comment parent not found');
					$result[] = $DR->get();

					// We don't seem to have a copy of this conversation or at least the parent
					// - so request a copy of the entire conversation to date.
					// Don't do this if it's a relay post as we're the ones who are supposed to
					// have the copy and we don't want the request to loop.
					// Also don't do this if this comment came from a conversation request packet.
					// It's possible that comments are allowed but posting isn't and that could
					// cause a conversation fetch loop. We can detect these packets since they are
					// delivered via a 'notify' packet type that has a message_id element in the
					// initial zot packet (just like the corresponding 'request' packet type which
					// makes the request).
					// We'll also check the send_stream permission - because if it isn't allowed,
					// the top level post is unlikely to be imported and
					// this is just an exercise in futility.

					if((! $relay) && (! $request) && (! $local_public)
						&& perm_is_allowed($channel['channel_id'],$sender['hash'],'send_stream')) {
						\Zotlabs\Daemon\Master::Summon(array('Notifier', 'request', $channel['channel_id'], $sender['hash'], $arr['parent_mid']));
					}
					continue;
				}
				if($relay) {
					// reset the route in case it travelled a great distance upstream
					// use our parent's route so when we go back downstream we'll match
					// with whatever route our parent has.
					$arr['route'] = $r[0]['route'];
				}
				else {

					// going downstream check that we have the same upstream provider that
					// sent it to us originally. Ignore it if it came from another source
					// (with potentially different permissions).
					// only compare the last hop since it could have arrived at the last location any number of ways.
					// Always accept empty routes and firehose items (route contains 'undefined') .

					$existing_route = explode(',', $r[0]['route']);
					$routes = count($existing_route);
					if($routes) {
						$last_hop = array_pop($existing_route);
						$last_prior_route = implode(',',$existing_route);
					}
					else {
						$last_hop = '';
						$last_prior_route = '';
					}

					if(in_array('undefined',$existing_route) || $last_hop == 'undefined' || $sender['hash'] == 'undefined')
						$last_hop = '';

					$current_route = (($arr['route']) ? $arr['route'] . ',' : '') . $sender['hash'];

					if($last_hop && $last_hop != $sender['hash']) {
						logger('comment route mismatch: parent route = ' . $r[0]['route'] . ' expected = ' . $current_route, LOGGER_DEBUG);
						logger('comment route mismatch: parent msg = ' . $r[0]['id'],LOGGER_DEBUG);
						$DR->update('comment route mismatch');
						$result[] = $DR->get();
						continue;
					}

					// we'll add sender['hash'] onto this when we deliver it. $last_prior_route now has the previously stored route
					// *except* for the sender['hash'] which would've been the last hop before it got to us.

					$arr['route'] = $last_prior_route;
				}
			}

			$ab = q("select * from abook where abook_channel = %d and abook_xchan = '%s'",
				intval($channel['channel_id']),
				dbesc($arr['owner_xchan'])
			);
			$abook = (($ab) ? $ab[0] : null);

			if(intval($arr['item_deleted'])) {

				// remove_community_tag is a no-op if this isn't a community tag activity
				self::remove_community_tag($sender,$arr,$channel['channel_id']);
	
				// set these just in case we need to store a fresh copy of the deleted post.
				// This could happen if the delete got here before the original post did.

				$arr['aid'] = $channel['channel_account_id'];
				$arr['uid'] = $channel['channel_id'];
	
				$item_id = delete_imported_item($sender,$arr,$channel['channel_id'],$relay);
				$DR->update(($item_id) ? 'deleted' : 'delete_failed');
				$result[] = $DR->get();

				if($relay && $item_id) {
					logger('process_delivery: invoking relay');
					\Zotlabs\Daemon\Master::Summon(array('Notifier','relay',intval($item_id)));
					$DR->update('relayed');
					$result[] = $DR->get();
				}

				continue;
			}


			$r = q("select * from item where mid = '%s' and uid = %d limit 1",
				dbesc($arr['mid']),
				intval($channel['channel_id'])
			);
			if($r) {
				// We already have this post.
				$item_id = $r[0]['id'];

				if(intval($r[0]['item_deleted'])) {
					// It was deleted locally.
					$DR->update('update ignored');
					$result[] = $DR->get();

					continue;
				}
				// Maybe it has been edited?
				elseif($arr['edited'] > $r[0]['edited']) {
					$arr['id'] = $r[0]['id'];
					$arr['uid'] = $channel['channel_id'];
					if(($arr['mid'] == $arr['parent_mid']) && (! post_is_importable($arr,$abook))) {
						$DR->update('update ignored');
						$result[] = $DR->get();
					}
					else {
						$item_result = update_imported_item($sender,$arr,$r[0],$channel['channel_id'],$tag_delivery);
						$DR->update('updated');
						$result[] = $DR->get();
						if(! $relay)
							add_source_route($item_id,$sender['hash']);
					}
				}
				else {
					$DR->update('update ignored');
					$result[] = $DR->get();

					// We need this line to ensure wall-to-wall comments are relayed (by falling through to the relay bit),
					// and at the same time not relay any other relayable posts more than once, because to do so is very wasteful.
					if(! intval($r[0]['item_origin']))
						continue;
				}
			}
			else {
				$arr['aid'] = $channel['channel_account_id'];
				$arr['uid'] = $channel['channel_id'];

				// if it's a sourced post, call the post_local hooks as if it were
				// posted locally so that crosspost connectors will be triggered.

				if(check_item_source($arr['uid'], $arr)) {
					/**
					 * @hooks post_local
					 *   Called when an item has been posted on this machine via mod/item.php (also via API).
					 *   * \e array with an item
					 */
					call_hooks('post_local', $arr);
				}

				$item_id = 0;

				if(($arr['mid'] == $arr['parent_mid']) && (! post_is_importable($arr,$abook))) {
					$DR->update('post ignored');
					$result[] = $DR->get();
				}
				else {
					$item_result = item_store($arr);
					if($item_result['success']) {
						$item_id = $item_result['item_id'];
						$parr = [
								'item_id' => $item_id,
								'item' => $arr,
								'sender' => $sender,
								'channel' => $channel
						];
						/**
						 * @hooks activity_received
						 *   Called when an activity (post, comment, like, etc.) has been received from a zot source.
						 *   * \e int \b item_id
						 *   * \e array \b item
						 *   * \e array \b sender
						 *   * \e array \b channel
						 */	
						call_hooks('activity_received', $parr);
						// don't add a source route if it's a relay or later recipients will get a route mismatch
						if(! $relay)
							add_source_route($item_id,$sender['hash']);
					}
					$DR->update(($item_id) ? 'posted' : 'storage failed: ' . $item_result['message']);
					$result[] = $DR->get();
				}
			}

			// preserve conversations with which you are involved from expiration

			$stored = (($item_result && $item_result['item']) ? $item_result['item'] : false);
			if((is_array($stored)) && ($stored['id'] != $stored['parent'])
				&& ($stored['author_xchan'] === $channel['channel_hash'])) {
				retain_item($stored['item']['parent']);
			}

			if($relay && $item_id) {
				logger('Invoking relay');
				\Zotlabs\Daemon\Master::Summon(array('Notifier','relay',intval($item_id)));
				$DR->addto_update('relayed');
				$result[] = $DR->get();
			}
		}

		if(! $deliveries)
			$result[] = array('', 'no recipients', '', $arr['mid']);

		logger('Local results: ' . print_r($result, true), LOGGER_DEBUG);

		return $result;
	}

	/**
	 * @brief Remove community tag.
	 *
	 * @param array $sender an associative array with
	 *   * \e string \b hash a xchan_hash
	 * @param array $arr an associative array
	 *   * \e int \b verb
	 *   * \e int \b obj_type
	 *   * \e int \b mid
	 * @param int $uid
	 */

	static function remove_community_tag($sender, $arr, $uid) {

		if(! (activity_match($arr['verb'], ACTIVITY_TAG) && ($arr['obj_type'] == ACTIVITY_OBJ_TAGTERM)))
			return;

		logger('remove_community_tag: invoked');

		if(! get_pconfig($uid,'system','blocktags')) {
			logger('Permission denied.');
			return;
		}

		$r = q("select * from item where mid = '%s' and uid = %d limit 1",
			dbesc($arr['mid']),
			intval($uid)
		);
		if(! $r) {
			logger('No item');
			return;
		}

		if(($sender['hash'] != $r[0]['owner_xchan']) && ($sender['hash'] != $r[0]['author_xchan'])) {
			logger('Sender not authorised.');
			return;
		}

		$i = $r[0];
	
		if($i['target'])
			$i['target'] = json_decode($i['target'],true);
		if($i['object'])
			$i['object'] = json_decode($i['object'],true);

		if(! ($i['target'] && $i['object'])) {
			logger('No target/object');
			return;
		}

		$message_id = $i['target']['id'];

		$r = q("select id from item where mid = '%s' and uid = %d limit 1",
			dbesc($message_id),
			intval($uid)
		);
		if(! $r) {
			logger('No parent message');
			return;
		}

		q("delete from term where uid = %d and oid = %d and otype = %d and ttype in  ( %d, %d ) and term = '%s' and url = '%s'",
			intval($uid),
			intval($r[0]['id']),
			intval(TERM_OBJ_POST),
			intval(TERM_HASHTAG),
			intval(TERM_COMMUNITYTAG),
			dbesc($i['object']['title']),
			dbesc(get_rel_link($i['object']['link'],'alternate'))
		);
	}

	/**
	 * @brief Updates an imported item.
	 *
	 * @see item_store_update()
	 *
	 * @param array $sender
	 * @param array $item
	 * @param array $orig
	 * @param int $uid
	 * @param boolean $tag_delivery
	 */
	
	static function update_imported_item($sender, $item, $orig, $uid, $tag_delivery) {

		// If this is a comment being updated, remove any privacy information
		// so that item_store_update will set it from the original.

		if($item['mid'] !== $item['parent_mid']) {
			unset($item['allow_cid']);
			unset($item['allow_gid']);
			unset($item['deny_cid']);
			unset($item['deny_gid']);
			unset($item['item_private']);
		}

		// we need the tag_delivery check for downstream flowing posts as the stored post
		// may have a different owner than the one being transmitted.

		if(($sender['hash'] != $orig['owner_xchan'] && $sender['hash'] != $orig['author_xchan']) && (! $tag_delivery)) {
			logger('sender is not owner or author');
			return;
		}


		$x = item_store_update($item);

		// If we're updating an event that we've saved locally, we store the item info first
		// because event_addtocal will parse the body to get the 'new' event details

		if($orig['resource_type'] === 'event') {
			$res = event_addtocal($orig['id'], $uid);
			if(! $res)
				logger('update event: failed');
		}

		if(! $x['item_id'])
			logger('update_imported_item: failed: ' . $x['message']);
		else
			logger('update_imported_item');

		return $x;
	}

	/**
	 * @brief Deletes an imported item.
	 *
	 * @param array $sender
	 *   * \e string \b hash a xchan_hash
	 * @param array $item
	 * @param int $uid
	 * @param boolean $relay
	 * @return boolean|int post_id
	 */

	static function delete_imported_item($sender, $item, $uid, $relay) {

		logger('invoked', LOGGER_DEBUG);

		$ownership_valid = false;
		$item_found = false;
		$post_id = 0;

		$r = q("select id, author_xchan, owner_xchan, source_xchan, item_deleted from item where ( author_xchan = '%s' or owner_xchan = '%s' or source_xchan = '%s' )
			and mid = '%s' and uid = %d limit 1",
			dbesc($sender['hash']),
			dbesc($sender['hash']),
			dbesc($sender['hash']),
			dbesc($item['mid']),
			intval($uid)
		);

		if($r) {
			if($r[0]['author_xchan'] === $sender['hash'] || $r[0]['owner_xchan'] === $sender['hash'] || $r[0]['source_xchan'] === $sender['hash'])
				$ownership_valid = true;

			$post_id = $r[0]['id'];
			$item_found = true;
		}
		else {

			// perhaps the item is still in transit and the delete notification got here before the actual item did. Store it with the deleted flag set.
			// item_store() won't try to deliver any notifications or start delivery chains if this flag is set.
			// This means we won't end up with potentially even more delivery threads trying to push this delete notification.
			// But this will ensure that if the (undeleted) original post comes in at a later date, we'll reject it because it will have an older timestamp.

			logger('delete received for non-existent item - storing item data.');

			if($item['author_xchan'] === $sender['hash'] || $item['owner_xchan'] === $sender['hash'] || $item['source_xchan'] === $sender['hash']) {
				$ownership_valid = true;
				$item_result = item_store($item);
				$post_id = $item_result['item_id'];
			}
		}

		if($ownership_valid === false) {
			logger('delete_imported_item: failed: ownership issue');
			return false;
		}

		if($item_found) {
			if(intval($r[0]['item_deleted'])) {
				logger('delete_imported_item: item was already deleted');
				if(! $relay)
					return false;

				// This is a bit hackish, but may have to suffice until the notification/delivery loop is optimised
				// a bit further. We're going to strip the ITEM_ORIGIN on this item if it's a comment, because
				// it was already deleted, and we're already relaying, and this ensures that no other process or
				// code path downstream can relay it again (causing a loop). Since it's already gone it's not coming
				// back, and we aren't going to (or shouldn't at any rate) delete it again in the future - so losing
				// this information from the metadata should have no other discernible impact.

				if (($r[0]['id'] != $r[0]['parent']) && intval($r[0]['item_origin'])) {
					q("update item set item_origin = 0 where id = %d and uid = %d",
						intval($r[0]['id']),
						intval($r[0]['uid'])
					);
				}
			}


			// Use phased deletion to set the deleted flag, call both tag_deliver and the notifier to notify downstream channels
			// and then clean up after ourselves with a cron job after several days to do the delete_item_lowlevel() (DROPITEM_PHASE2).

			drop_item($post_id, false, DROPITEM_PHASE1);
			tag_deliver($uid, $post_id);
		}

		return $post_id;
	}

	static function process_mail_delivery($sender, $arr, $deliveries) {

		$result = array();

		if($sender['hash'] != $arr['from_xchan']) {
			logger('process_mail_delivery: sender is not mail author');
			return;
		}

		foreach($deliveries as $d) {
	
			$DR = new \Zotlabs\Lib\DReport(z_root(),$sender['hash'],$d['hash'],$arr['mid']);

			$r = q("select * from channel where channel_hash = '%s' limit 1",
				dbesc($d['hash'])
			);

			if(! $r) {
				$DR->update('recipient not found');
				$result[] = $DR->get();
				continue;
			}

			$channel = $r[0];
			$DR->addto_recipient($channel['channel_name'] . ' <' . channel_reddress($channel) . '>');

			/* blacklisted channels get a permission denied, no special message to tip them off */

			if(! check_channelallowed($sender['hash'])) {
				$DR->update('permission denied');
				$result[] = $DR->get();
				continue;
			}


			if(! perm_is_allowed($channel['channel_id'],$sender['hash'],'post_mail')) {

				/* 
				 * Always allow somebody to reply if you initiated the conversation. It's anti-social
				 * and a bit rude to send a private message to somebody and block their ability to respond.
				 * If you are being harrassed and want to put an end to it, delete the conversation.
				 */

				$return = false;
				if($arr['parent_mid']) {
					$return = q("select * from mail where mid = '%s' and channel_id = %d limit 1",
						dbesc($arr['parent_mid']),
						intval($channel['channel_id'])
					);
				}
				if(! $return) {
					logger("permission denied for mail delivery {$channel['channel_id']}");
					$DR->update('permission denied');
					$result[] = $DR->get();
					continue;
				}
			}


			$r = q("select id from mail where mid = '%s' and channel_id = %d limit 1",
				dbesc($arr['mid']),
				intval($channel['channel_id'])
			);
			if($r) {
				if(intval($arr['mail_recalled'])) {
					$x = q("delete from mail where id = %d and channel_id = %d",
						intval($r[0]['id']),
						intval($channel['channel_id'])
					);
					$DR->update('mail recalled');
					$result[] = $DR->get();
					logger('mail_recalled');
				}
				else {
					$DR->update('duplicate mail received');
					$result[] = $DR->get();
					logger('duplicate mail received');
				}
				continue;
			}
			else {
				$arr['account_id'] = $channel['channel_account_id'];
				$arr['channel_id'] = $channel['channel_id'];
				$item_id = mail_store($arr);
				$DR->update('mail delivered');
				$result[] = $DR->get();
			}
		}

		return $result;
	}

	/**
	 * @brief Processes delivery of rating.
	 *
	 * @param array $sender
	 *   * \e string \b hash a xchan_hash
	 * @param array $arr
	 */

	static function process_rating_delivery($sender, $arr) {

		logger('process_rating_delivery: ' . print_r($arr,true));

		if(! $arr['target'])
			return;

		$z = q("select xchan_pubkey from xchan where xchan_hash = '%s' limit 1",
			dbesc($sender['hash'])
		);

		if((! $z) || (! zot_verify($arr['target'] . '.' . $arr['rating'] . '.' . $arr['rating_text'], $arr['signature'],$z[0]['xchan_pubkey']))) {
			logger('failed to verify rating');
			return;
		}

		$r = q("select * from xlink where xlink_xchan = '%s' and xlink_link = '%s' and xlink_static = 1 limit 1",
			dbesc($sender['hash']),
			dbesc($arr['target'])
		);

		if($r) {
			if($r[0]['xlink_updated'] >= $arr['edited']) {
				logger('rating message duplicate');
				return;
			}

			$x = q("update xlink set xlink_rating = %d, xlink_rating_text = '%s', xlink_sig = '%s', xlink_updated = '%s' where xlink_id = %d",
				intval($arr['rating']),
				dbesc($arr['rating_text']),
				dbesc($arr['signature']),
				dbesc(datetime_convert()),
				intval($r[0]['xlink_id'])
			);
			logger('rating updated');
		}
		else {
			$x = q("insert into xlink ( xlink_xchan, xlink_link, xlink_rating, xlink_rating_text, xlink_sig, xlink_updated, xlink_static )
				values( '%s', '%s', %d, '%s', '%s', '%s', 1 ) ",
				dbesc($sender['hash']),
				dbesc($arr['target']),
				intval($arr['rating']),
				dbesc($arr['rating_text']),
				dbesc($arr['signature']),
				dbesc(datetime_convert())
			);
			logger('rating created');
		}
	}

	/**
	 * @brief Processes delivery of profile.
	 *
	 * @see import_directory_profile()
	 * @param array $sender an associative array
	 *   * \e string \b hash a xchan_hash
	 * @param array $arr
	 * @param array $deliveries (unused)
	 */

	static function process_profile_delivery($sender, $arr, $deliveries) {

		logger('process_profile_delivery', LOGGER_DEBUG);

		$r = q("select xchan_addr from xchan where xchan_hash = '%s' limit 1",
				dbesc($sender['hash'])
		);
		if($r)
			import_directory_profile($sender['hash'], $arr, $r[0]['xchan_addr'], UPDATE_FLAGS_UPDATED, 0);
	}


	/**
	 * @brief
	 *
	 * @param array $sender an associative array
	 *   * \e string \b hash a xchan_hash
	 * @param array $arr
	 * @param array $deliveries (unused) deliveries is irrelevant
	 */
	static function process_location_delivery($sender, $arr, $deliveries) {

		// deliveries is irrelevant
		logger('process_location_delivery', LOGGER_DEBUG);

		$r = q("select xchan_pubkey from xchan where xchan_hash = '%s' limit 1",
			dbesc($sender['hash'])
		);
		if($r)
			$sender['key'] = $r[0]['xchan_pubkey'];

		if(array_key_exists('locations',$arr) && $arr['locations']) {
			$x = sync_locations($sender,$arr,true);
			logger('results: ' . print_r($x,true), LOGGER_DEBUG);
			if($x['changed']) {
				$guid = random_string() . '@' . App::get_hostname();
				update_modtime($sender['hash'],$sender['id'],$arr['locations'][0]['address'],UPDATE_FLAGS_UPDATED);
			}
		}
	}

	/**
	 * @brief Checks for a moved UNO channel and sets the channel_moved flag.
	 *
	 * Currently the effect of this flag is to turn the channel into 'read-only' mode.
	 * New content will not be processed (there was still an issue with blocking the
	 * ability to post comments as of 10-Mar-2016).
	 * We do not physically remove the channel at this time. The hub admin may choose
	 * to do so, but is encouraged to allow a grace period of several days in case there
	 * are any issues migrating content. This packet will generally be received by the
	 * original site when the basic channel import has been processed.
	 *
	 * This will only be executed on the UNO system which is the old location
	 * if a new location is reported and there is only one location record.
	 * The rest of the hubloc syncronisation will be handled within
	 * sync_locations
	 *
	 * @param string $sender_hash A channel hash
	 * @param array $locations
	 */

	static function check_location_move($sender_hash, $locations) {

		if(! $locations)
			return;

		if(count($locations) != 1)
			return;

		$loc = $locations[0];

		$r = q("select * from channel where channel_hash = '%s' limit 1",
			dbesc($sender_hash)
		);

		if(! $r)
			return;

		if($loc['url'] !== z_root()) {
			$x = q("update channel set channel_moved = '%s' where channel_hash = '%s' limit 1",
				dbesc($loc['url']),
				dbesc($sender_hash)
			);

			// federation plugins may wish to notify connections
			// of the move on singleton networks

			$arr = [
				'channel' => $r[0],
				'locations' => $locations
			];
			/**
			 * @hooks location_move
			 *   Called when a new location has been provided to a UNO channel (indicating a move rather than a clone).
			 *   * \e array \b channel
			 *   * \e array \b locations
			 */
			call_hooks('location_move', $arr);
		}
	}


	/**
	 * @brief Synchronises locations.
	 *
	 * @param array $sender
	 * @param array $arr
	 * @param boolean $absolute (optional) default false
	 * @return array
	 */

	static function sync_locations($sender, $arr, $absolute = false) {

		$ret = array();

		if($arr['locations']) {

			if($absolute)
				self::check_location_move($sender['hash'],$arr['locations']);

			$xisting = q("select hubloc_id, hubloc_url, hubloc_sitekey from hubloc where hubloc_hash = '%s'",
				dbesc($sender['hash'])
			);

			// See if a primary is specified

			$has_primary = false;
			foreach($arr['locations'] as $location) {
				if($location['primary']) {
					$has_primary = true;
					break;
				}
			}

			// Ensure that they have one primary hub

			if(! $has_primary)
				$arr['locations'][0]['primary'] = true;

			foreach($arr['locations'] as $location) {
				if(! self::verify($location['url'],$location['url_sig'],$sender['public_key'])) {
					logger('Unable to verify site signature for ' . $location['url']);
					$ret['message'] .= sprintf( t('Unable to verify site signature for %s'), $location['url']) . EOL;
					continue;
				}

				for($x = 0; $x < count($xisting); $x ++) {
					if(($xisting[$x]['hubloc_url'] === $location['url'])
						&& ($xisting[$x]['hubloc_sitekey'] === $location['sitekey'])) {
						$xisting[$x]['updated'] = true;
					}
				}

				if(! $location['sitekey']) {
					logger('Empty hubloc sitekey. ' . print_r($location,true));
					continue;
				}

				// Catch some malformed entries from the past which still exist

				if(strpos($location['address'],'/') !== false)
					$location['address'] = substr($location['address'],0,strpos($location['address'],'/'));

				// match as many fields as possible in case anything at all changed.

				$r = q("select * from hubloc where hubloc_hash = '%s' and hubloc_guid = '%s' and hubloc_guid_sig = '%s' and hubloc_id_url = '%s' and hubloc_url = '%s' and hubloc_url_sig = '%s' and hubloc_site_id = '%s' and hubloc_host = '%s' and hubloc_addr = '%s' and hubloc_callback = '%s' and hubloc_sitekey = '%s' ",
					dbesc($sender['hash']),
					dbesc($sender['id']),
					dbesc($sender['id_sig']),
					dbesc($location['id_url']),
					dbesc($location['url']),
					dbesc($location['url_sig']),
					dbesc($location['site_id']),
					dbesc($location['host']),
					dbesc($location['address']),
					dbesc($location['callback']),
					dbesc($location['sitekey'])
				);
				if($r) {
					logger('Hub exists: ' . $location['url'], LOGGER_DEBUG);
	
					// update connection timestamp if this is the site we're talking to
					// This only happens when called from import_xchan

					$current_site = false;

					$t = datetime_convert('UTC','UTC','now - 15 minutes');
	
					if(array_key_exists('site',$arr) && $location['url'] == $arr['site']['url']) {
						q("update hubloc set hubloc_connected = '%s', hubloc_updated = '%s' where hubloc_id = %d and hubloc_connected < '%s'",
							dbesc(datetime_convert()),
							dbesc(datetime_convert()),
							intval($r[0]['hubloc_id']),
							dbesc($t)
						);
						$current_site = true;
					}

					if($current_site && intval($r[0]['hubloc_error'])) {
						q("update hubloc set hubloc_error = 0 where hubloc_id = %d",
							intval($r[0]['hubloc_id'])
						);
						if(intval($r[0]['hubloc_orphancheck'])) {
							q("update hubloc set hubloc_orphancheck = 0 where hubloc_id = %d",
								intval($r[0]['hubloc_id'])
							);
						}
						q("update xchan set xchan_orphan = 0 where xchan_orphan = 1 and xchan_hash = '%s'",
							dbesc($sender['hash'])
						);
					}

					// Remove pure duplicates
					if(count($r) > 1) {
						for($h = 1; $h < count($r); $h ++) {
							q("delete from hubloc where hubloc_id = %d",
								intval($r[$h]['hubloc_id'])
							);
							$what .= 'duplicate_hubloc_removed ';
							$changed = true;
						}
					}

					if(intval($r[0]['hubloc_primary']) && (! $location['primary'])) {
						$m = q("update hubloc set hubloc_primary = 0, hubloc_updated = '%s' where hubloc_id = %d",
							dbesc(datetime_convert()),
							intval($r[0]['hubloc_id'])
						);
						$r[0]['hubloc_primary'] = intval($location['primary']);
						hubloc_change_primary($r[0]);
						$what .= 'primary_hub ';
						$changed = true;
					}
					elseif((! intval($r[0]['hubloc_primary'])) && ($location['primary'])) {
						$m = q("update hubloc set hubloc_primary = 1, hubloc_updated = '%s' where hubloc_id = %d",
							dbesc(datetime_convert()),
							intval($r[0]['hubloc_id'])
						);
						// make sure hubloc_change_primary() has current data
						$r[0]['hubloc_primary'] = intval($location['primary']);
						hubloc_change_primary($r[0]);
						$what .= 'primary_hub ';
						$changed = true;
					}
					elseif($absolute) {
						// Absolute sync - make sure the current primary is correctly reflected in the xchan
						$pr = hubloc_change_primary($r[0]);
						if($pr) {
							$what .= 'xchan_primary ';
							$changed = true;
						}
					}
					if(intval($r[0]['hubloc_deleted']) && (! intval($location['deleted']))) {
						$n = q("update hubloc set hubloc_deleted = 0, hubloc_updated = '%s' where hubloc_id = %d",
							dbesc(datetime_convert()),
							intval($r[0]['hubloc_id'])
						);
						$what .= 'undelete_hub ';
						$changed = true;
					}
					elseif((! intval($r[0]['hubloc_deleted'])) && (intval($location['deleted']))) {
						logger('deleting hubloc: ' . $r[0]['hubloc_addr']);
						$n = q("update hubloc set hubloc_deleted = 1, hubloc_updated = '%s' where hubloc_id = %d",
							dbesc(datetime_convert()),
							intval($r[0]['hubloc_id'])
						);
						$what .= 'delete_hub ';
						$changed = true;
					}
					continue;
				}

				// Existing hubs are dealt with. Now let's process any new ones.
				// New hub claiming to be primary. Make it so by removing any existing primaries.

				if(intval($location['primary'])) {
					$r = q("update hubloc set hubloc_primary = 0, hubloc_updated = '%s' where hubloc_hash = '%s' and hubloc_primary = 1",
						dbesc(datetime_convert()),
						dbesc($sender['hash'])
					);
				}
				logger('New hub: ' . $location['url']);

				$r = hubloc_store_lowlevel(
					[
						'hubloc_guid'      => $sender['id'],
						'hubloc_guid_sig'  => $sender['id_sig'],
						'hubloc_id_url'    => $location['id_url'],
						'hubloc_hash'      => $sender['hash'],
						'hubloc_addr'      => $location['address'],
						'hubloc_network'   => 'zot6',
						'hubloc_primary'   => intval($location['primary']),
						'hubloc_url'       => $location['url'],
						'hubloc_url_sig'   => $location['url_sig'],
						'hubloc_site_id'   => self::make_xchan_hash($location['url'],$location['sitekey']),
						'hubloc_host'      => $location['host'],
						'hubloc_callback'  => $location['callback'],
						'hubloc_sitekey'   => $location['sitekey'],
						'hubloc_updated'   => datetime_convert(),
						'hubloc_connected' => datetime_convert()
					]
				);

				$what .= 'newhub ';
				$changed = true;

				if($location['primary']) {
					$r = q("select * from hubloc where hubloc_addr = '%s' and hubloc_sitekey = '%s' limit 1",
						dbesc($location['address']),
						dbesc($location['sitekey'])
					);
					if($r)
						hubloc_change_primary($r[0]);
				}
			}

			// get rid of any hubs we have for this channel which weren't reported.

			if($absolute && $xisting) {
				foreach($xisting as $x) {
					if(! array_key_exists('updated',$x)) {
						logger('Deleting unreferenced hub location ' . $x['hubloc_addr']);
						$r = q("update hubloc set hubloc_deleted = 1, hubloc_updated = '%s' where hubloc_id = %d",
							dbesc(datetime_convert()),
							intval($x['hubloc_id'])
						);
						$what .= 'removed_hub ';
						$changed = true;
					}
				}
			}
		}
		else {
			logger('No locations to sync!');
		}

		$ret['change_message'] = $what;
		$ret['changed'] = $changed;

		return $ret;
	}

	/**
	 * @brief Returns an array with all known distinct hubs for this channel.
	 *
	 * @see self::get_hublocs()
	 * @param array $channel an associative array which must contain
	 *  * \e string \b channel_hash the hash of the channel
	 * @return array an array with associative arrays
	 */

	static function encode_locations($channel) {
		$ret = [];

		$x = self::get_hublocs($channel['channel_hash']);

		if($x && count($x)) {
			foreach($x as $hub) {

				// if this is a local channel that has been deleted, the hubloc is no good - make sure it is marked deleted
				// so that nobody tries to use it.

				if(intval($channel['channel_removed']) && $hub['hubloc_url'] === z_root())
					$hub['hubloc_deleted'] = 1;

				$ret[] = [
					'host'     => $hub['hubloc_host'],
					'address'  => $hub['hubloc_addr'],
					'id_url'   => $hub['hubloc_id_url'],
					'primary'  => (intval($hub['hubloc_primary']) ? true : false),
					'url'      => $hub['hubloc_url'],
					'url_sig'  => $hub['hubloc_url_sig'],
					'site_id'  => $hub['hubloc_site_id'],
					'callback' => $hub['hubloc_callback'],
					'sitekey'  => $hub['hubloc_sitekey'],
					'deleted'  => (intval($hub['hubloc_deleted']) ? true : false)
				];
			}
		}

		return $ret;
	}

	/**
	 * @brief Imports a directory profile.
	 *
	 * @param string $hash
	 * @param array $profile
	 * @param string $addr
	 * @param number $ud_flags (optional) UPDATE_FLAGS_UPDATED
	 * @param number $suppress_update (optional) default 0
	 * @return boolean $updated if something changed
	 */

	static function import_directory_profile($hash, $profile, $addr, $ud_flags = UPDATE_FLAGS_UPDATED, $suppress_update = 0) {

		logger('import_directory_profile', LOGGER_DEBUG);
		if (! $hash)
			return false;

		$arr = array();

		$arr['xprof_hash']         = $hash;
		$arr['xprof_dob']          = (($profile['birthday'] === '0000-00-00') ? $profile['birthday'] : datetime_convert('','',$profile['birthday'],'Y-m-d')); // !!!! check this for 0000 year
		$arr['xprof_age']          = (($profile['age'])         ? intval($profile['age']) : 0);
		$arr['xprof_desc']         = (($profile['description']) ? htmlspecialchars($profile['description'], ENT_COMPAT,'UTF-8',false) : '');	
		$arr['xprof_gender']       = (($profile['gender'])      ? htmlspecialchars($profile['gender'],      ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_marital']      = (($profile['marital'])     ? htmlspecialchars($profile['marital'],     ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_sexual']       = (($profile['sexual'])      ? htmlspecialchars($profile['sexual'],      ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_locale']       = (($profile['locale'])      ? htmlspecialchars($profile['locale'],      ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_region']       = (($profile['region'])      ? htmlspecialchars($profile['region'],      ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_postcode']     = (($profile['postcode'])    ? htmlspecialchars($profile['postcode'],    ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_country']      = (($profile['country'])     ? htmlspecialchars($profile['country'],     ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_about']        = (($profile['about'])       ? htmlspecialchars($profile['about'],       ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_homepage']     = (($profile['homepage'])    ? htmlspecialchars($profile['homepage'],    ENT_COMPAT,'UTF-8',false) : '');
		$arr['xprof_hometown']     = (($profile['hometown'])    ? htmlspecialchars($profile['hometown'],    ENT_COMPAT,'UTF-8',false) : '');

		$clean = array();
		if (array_key_exists('keywords', $profile) and is_array($profile['keywords'])) {
			self::import_directory_keywords($hash,$profile['keywords']);
			foreach ($profile['keywords'] as $kw) {
				$kw = trim(htmlspecialchars($kw,ENT_COMPAT, 'UTF-8', false));
				$kw = trim($kw, ',');
				$clean[] = $kw;
			}
		}

		$arr['xprof_keywords'] = implode(' ',$clean);

		// Self censored, make it so
		// These are not translated, so the German "erwachsenen" keyword will not censor the directory profile. Only the English form - "adult".


		if(in_arrayi('nsfw',$clean) || in_arrayi('adult',$clean)) {
			q("update xchan set xchan_selfcensored = 1 where xchan_hash = '%s'",
				dbesc($hash)
			);
		}

		$r = q("select * from xprof where xprof_hash = '%s' limit 1",
			dbesc($hash)
		);

		if ($arr['xprof_age'] > 150)
			$arr['xprof_age'] = 150;
		if ($arr['xprof_age'] < 0)
			$arr['xprof_age'] = 0;

		if ($r) {
			$update = false;
			foreach ($r[0] as $k => $v) {
				if ((array_key_exists($k,$arr)) && ($arr[$k] != $v)) {
					logger('import_directory_profile: update ' . $k . ' => ' . $arr[$k]);
					$update = true;
					break;
				}
			}
			if ($update) {
				q("update xprof set
					xprof_desc = '%s',
					xprof_dob = '%s',
					xprof_age = %d,
					xprof_gender = '%s',
					xprof_marital = '%s',
					xprof_sexual = '%s',
					xprof_locale = '%s',
					xprof_region = '%s',
					xprof_postcode = '%s',
					xprof_country = '%s',
					xprof_about = '%s',
					xprof_homepage = '%s',
					xprof_hometown = '%s',
					xprof_keywords = '%s'
					where xprof_hash = '%s'",
					dbesc($arr['xprof_desc']),
					dbesc($arr['xprof_dob']),
					intval($arr['xprof_age']),
					dbesc($arr['xprof_gender']),
					dbesc($arr['xprof_marital']),
					dbesc($arr['xprof_sexual']),
					dbesc($arr['xprof_locale']),
					dbesc($arr['xprof_region']),
					dbesc($arr['xprof_postcode']),
					dbesc($arr['xprof_country']),
					dbesc($arr['xprof_about']),
					dbesc($arr['xprof_homepage']),
					dbesc($arr['xprof_hometown']),
					dbesc($arr['xprof_keywords']),
					dbesc($arr['xprof_hash'])
				);
			}
		} else {
			$update = true;
			logger('New profile');
			q("insert into xprof (xprof_hash, xprof_desc, xprof_dob, xprof_age, xprof_gender, xprof_marital, xprof_sexual, xprof_locale, xprof_region, xprof_postcode, xprof_country, xprof_about, xprof_homepage, xprof_hometown, xprof_keywords) values ('%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s') ",
				dbesc($arr['xprof_hash']),
				dbesc($arr['xprof_desc']),
				dbesc($arr['xprof_dob']),
				intval($arr['xprof_age']),
				dbesc($arr['xprof_gender']),
				dbesc($arr['xprof_marital']),
				dbesc($arr['xprof_sexual']),
				dbesc($arr['xprof_locale']),
				dbesc($arr['xprof_region']),
				dbesc($arr['xprof_postcode']),
				dbesc($arr['xprof_country']),
				dbesc($arr['xprof_about']),
				dbesc($arr['xprof_homepage']),
				dbesc($arr['xprof_hometown']),
				dbesc($arr['xprof_keywords'])
			);
		}

		$d = [
			'xprof' => $arr,
			'profile' => $profile,
			'update' => $update
		];
		/**
		 * @hooks import_directory_profile
		 *   Called when processing delivery of a profile structure from an external source (usually for directory storage).
		 *   * \e array \b xprof
		 *   * \e array \b profile
		 *   * \e boolean \b update
		 */
		call_hooks('import_directory_profile', $d);

		if (($d['update']) && (! $suppress_update))
			self::update_modtime($arr['xprof_hash'],random_string() . '@' . App::get_hostname(), $addr, $ud_flags);

		return $d['update'];
	}

	/**
	 * @brief
	 *
	 * @param string $hash An xtag_hash
	 * @param array $keywords
	 */

	static function import_directory_keywords($hash, $keywords) {

		$existing = array();
		$r = q("select * from xtag where xtag_hash = '%s' and xtag_flags = 0",
			dbesc($hash)
		);

		if($r) {
			foreach($r as $rr)
				$existing[] = $rr['xtag_term'];
		}

		$clean = array();
		foreach($keywords as $kw) {
			$kw = trim(htmlspecialchars($kw,ENT_COMPAT, 'UTF-8', false));
			$kw = trim($kw, ',');
			$clean[] = $kw;
		}

		foreach($existing as $x) {
			if(! in_array($x, $clean))
				$r = q("delete from xtag where xtag_hash = '%s' and xtag_term = '%s' and xtag_flags = 0",
					dbesc($hash),
					dbesc($x)
				);
		}
		foreach($clean as $x) {
			if(! in_array($x, $existing)) {
				$r = q("insert into xtag ( xtag_hash, xtag_term, xtag_flags) values ( '%s' ,'%s', 0 )",
					dbesc($hash),
					dbesc($x)
				);
			}
		}
	}

	/**
	 * @brief
	 *
	 * @param string $hash
	 * @param string $guid
	 * @param string $addr
	 * @param int $flags (optional) default 0
	 */

	static function update_modtime($hash, $guid, $addr, $flags = 0) {

		$dirmode = intval(get_config('system', 'directory_mode'));

		if($dirmode == DIRECTORY_MODE_NORMAL)
			return;

		if($flags) {
			q("insert into updates (ud_hash, ud_guid, ud_date, ud_flags, ud_addr ) values ( '%s', '%s', '%s', %d, '%s' )",
				dbesc($hash),
				dbesc($guid),
				dbesc(datetime_convert()),
				intval($flags),
				dbesc($addr)
			);	
		}
		else {
			q("update updates set ud_flags = ( ud_flags | %d ) where ud_addr = '%s' and not (ud_flags & %d)>0 ",
				intval(UPDATE_FLAGS_UPDATED),
				dbesc($addr),
				intval(UPDATE_FLAGS_UPDATED)
			);
		}
	}

	/**
	 * @brief
	 *
	 * @param array $arr
	 * @param string $pubkey
	 * @return boolean true if updated or inserted
	 */
	
	static function import_site($arr, $pubkey) {
		if( (! is_array($arr)) || (! $arr['url']) || (! $arr['url_sig']))
			return false;

		if(! self::verify($arr['url'], $arr['url_sig'], $pubkey)) {
			logger('Bad url_sig');
			return false;
		}

		$update = false;
		$exists = false;

		$r = q("select * from site where site_url = '%s' limit 1",
			dbesc($arr['url'])
		);
		if($r) {
			$exists = true;
			$siterecord = $r[0];
		}

		$site_directory = 0;
		if($arr['directory_mode'] == 'normal')
			$site_directory = DIRECTORY_MODE_NORMAL;
		if($arr['directory_mode'] == 'primary')
			$site_directory = DIRECTORY_MODE_PRIMARY;
		if($arr['directory_mode'] == 'secondary')
			$site_directory = DIRECTORY_MODE_SECONDARY;
		if($arr['directory_mode'] == 'standalone')
			$site_directory = DIRECTORY_MODE_STANDALONE;

		$register_policy = 0;
		if($arr['register_policy'] == 'closed')
			$register_policy = REGISTER_CLOSED;
		if($arr['register_policy'] == 'open')
			$register_policy = REGISTER_OPEN;
		if($arr['register_policy'] == 'approve')
			$register_policy = REGISTER_APPROVE;

		$access_policy = 0;
		if(array_key_exists('access_policy',$arr)) {
			if($arr['access_policy'] === 'private')
				$access_policy = ACCESS_PRIVATE;
			if($arr['access_policy'] === 'paid')
				$access_policy = ACCESS_PAID;
			if($arr['access_policy'] === 'free')
				$access_policy = ACCESS_FREE;
			if($arr['access_policy'] === 'tiered')
				$access_policy = ACCESS_TIERED;
		}

		// don't let insecure sites register as public hubs

		if(strpos($arr['url'],'https://') === false)
			$access_policy = ACCESS_PRIVATE;

		if($access_policy != ACCESS_PRIVATE) {
			$x = z_fetch_url($arr['url'] . '/siteinfo.json');
			if(! $x['success'])
				$access_policy = ACCESS_PRIVATE;
		}

		$directory_url = htmlspecialchars($arr['directory_url'],ENT_COMPAT,'UTF-8',false);
		$url = htmlspecialchars(strtolower($arr['url']),ENT_COMPAT,'UTF-8',false);
		$sellpage = htmlspecialchars($arr['sellpage'],ENT_COMPAT,'UTF-8',false);
		$site_location = htmlspecialchars($arr['location'],ENT_COMPAT,'UTF-8',false);
		$site_realm = htmlspecialchars($arr['realm'],ENT_COMPAT,'UTF-8',false);
		$site_project = htmlspecialchars($arr['project'],ENT_COMPAT,'UTF-8',false);
		$site_crypto = ((array_key_exists('encryption',$arr) && is_array($arr['encryption'])) ? htmlspecialchars(implode(',',$arr['encryption']),ENT_COMPAT,'UTF-8',false) : '');
		$site_version = ((array_key_exists('version',$arr)) ? htmlspecialchars($arr['version'],ENT_COMPAT,'UTF-8',false) : '');

		// You can have one and only one primary directory per realm.
		// Downgrade any others claiming to be primary. As they have
		// flubbed up this badly already, don't let them be directory servers at all.

		if(($site_directory === DIRECTORY_MODE_PRIMARY)
			&& ($site_realm === get_directory_realm())
			&& ($arr['url'] != get_directory_primary())) {
			$site_directory = DIRECTORY_MODE_NORMAL;
		}

		$site_flags = $site_directory;

		if(array_key_exists('zot',$arr)) {
			set_sconfig($arr['url'],'system','zot_version',$arr['zot']);
		}

		if($exists) {
			if(($siterecord['site_flags'] != $site_flags)
				|| ($siterecord['site_access'] != $access_policy)
				|| ($siterecord['site_directory'] != $directory_url)
				|| ($siterecord['site_sellpage'] != $sellpage)
				|| ($siterecord['site_location'] != $site_location)
				|| ($siterecord['site_register'] != $register_policy)
				|| ($siterecord['site_project'] != $site_project)
				|| ($siterecord['site_realm'] != $site_realm)
				|| ($siterecord['site_crypto'] != $site_crypto)
				|| ($siterecord['site_version'] != $site_version)   ) {

				$update = true;

	//			logger('import_site: input: ' . print_r($arr,true));
	//			logger('import_site: stored: ' . print_r($siterecord,true));

				$r = q("update site set site_dead = 0, site_location = '%s', site_flags = %d, site_access = %d, site_directory = '%s', site_register = %d, site_update = '%s', site_sellpage = '%s', site_realm = '%s', site_type = %d, site_project = '%s', site_version = '%s', site_crypto = '%s'
					where site_url = '%s'",
					dbesc($site_location),
					intval($site_flags),
					intval($access_policy),
					dbesc($directory_url),
					intval($register_policy),
					dbesc(datetime_convert()),
					dbesc($sellpage),
					dbesc($site_realm),
					intval(SITE_TYPE_ZOT),
					dbesc($site_project),
					dbesc($site_version),
					dbesc($site_crypto),
					dbesc($url)
				);
				if(! $r) {
					logger('Update failed. ' . print_r($arr,true));
				}
			}
			else {
				// update the timestamp to indicate we communicated with this site
				q("update site set site_dead = 0, site_update = '%s' where site_url = '%s'",
					dbesc(datetime_convert()),
					dbesc($url)
				);
			}
		}
		else {
			$update = true;

			$r = site_store_lowlevel(
				[
					'site_location'  => $site_location,
					'site_url'       => $url,
					'site_access'    => intval($access_policy),
					'site_flags'     => intval($site_flags),
					'site_update'    => datetime_convert(),
					'site_directory' => $directory_url,
					'site_register'  => intval($register_policy),
					'site_sellpage'  => $sellpage,
					'site_realm'     => $site_realm,
					'site_type'      => intval(SITE_TYPE_ZOT),
					'site_project'   => $site_project,
					'site_version'   => $site_version,
					'site_crypto'    => $site_crypto
				]
			);

			if(! $r) {
				logger('Record create failed. ' . print_r($arr,true));
			}
		}

		return $update;
	}

	/**
	 * @brief Returns path to /rpost
	 *
	 * @todo We probably should make rpost discoverable.
	 *
	 * @param array $observer
	 *   * \e string \b xchan_url
	 * @return string
	 */
	static function get_rpost_path($observer) {
		if(! $observer)
			return '';

		$parsed = parse_url($observer['xchan_url']);

		return $parsed['scheme'] . '://' . $parsed['host'] . (($parsed['port']) ? ':' . $parsed['port'] : '') . '/rpost?f=';
	}

	/**
	 * @brief
	 *
	 * @param array $x
	 * @return boolean|string return false or a hash
	 */

	static function import_author_zot($x) {

		// Check that we have both a hubloc and xchan record - as occasionally storage calls will fail and
		// we may only end up with one; which results in posts with no author name or photo and are a bit
		// of a hassle to repair. If either or both are missing, do a full discovery probe.

		$hash = self::make_xchan_hash($x['id'],$x['key']);

		// also - this function may get passed a profile url as 'url' and zot_refresh wants a hubloc_url (site baseurl),
		// so deconstruct the url (if we have one) and rebuild it with just the baseurl components.

		if(array_key_exists('url',$x)) {
			$m = parse_url($x['url']);
			$desturl = $m['scheme'] . '://' . $m['host'];
		}

		$r1 = q("select hubloc_url, hubloc_updated, site_dead from hubloc left join site on
			hubloc_url = site_url where hubloc_guid = '%s' and hubloc_guid_sig = '%s' and hubloc_primary = 1 limit 1",
			dbesc($x['id']),
			dbesc($x['id_sig'])
		);

		$r2 = q("select xchan_hash from xchan where xchan_guid = '%s' and xchan_guid_sig = '%s' limit 1",
			dbesc($x['id']),
			dbesc($x['id_sig'])
		);

		$site_dead = false;

		if($r1 && intval($r1[0]['site_dead'])) {
			$site_dead = true;
		}

		// We have valid and somewhat fresh information.

		if($r1 && $r2 && $r1[0]['hubloc_updated'] > datetime_convert('UTC','UTC','now - 1 week')) {
			logger('in cache', LOGGER_DEBUG);
			return $hash;
		}

		logger('not in cache or cache stale - probing: ' . print_r($x,true), LOGGER_DEBUG,LOG_INFO);

		// The primary hub may be dead. Try to find another one associated with this identity that is
		// still alive. If we find one, use that url for the discovery/refresh probe. Otherwise, the dead site
		// is all we have and there is no point probing it. Just return the hash indicating we have a
		// cached entry and the identity is valid. It's just unreachable until they bring back their
		// server from the grave or create another clone elsewhere.

		if($site_dead) {
			logger('dead site - ignoring', LOGGER_DEBUG,LOG_INFO);

			$r = q("select hubloc_url from hubloc left join site on hubloc_url = site_url
				where hubloc_hash = '%s' and site_dead = 0",
				dbesc($hash)
			);
			if($r) {
				logger('found another site that is not dead: ' . $r[0]['hubloc_url'], LOGGER_DEBUG,LOG_INFO);
				$desturl = $r[0]['hubloc_url'];
			}
			else {
				return $hash;
			}
		}


		$them = array('hubloc_url' => $desturl, 'xchan_guid' => $x['id'], 'xchan_guid_sig' => $x['id_sig']);
		if(self::refresh($them))
			return $hash;

		return false;
	}



	static function zotinfo($arr) {

		$ret = [];


		$zhash     = ((x($arr,'guid_hash'))  ? $arr['guid_hash']   : '');
		$zguid     = ((x($arr,'guid'))       ? $arr['guid']        : '');
		$zguid_sig = ((x($arr,'guid_sig'))   ? $arr['guid_sig']    : '');
		$zaddr     = ((x($arr,'address'))    ? $arr['address']     : '');
		$ztarget   = ((x($arr,'target_url')) ? $arr['target_url']  : '');
		$zsig      = ((x($arr,'target_sig')) ? $arr['target_sig']  : '');
		$zkey      = ((x($arr,'key'))        ? $arr['key']         : '');
		$mindate   = ((x($arr,'mindate'))    ? $arr['mindate']     : '');
		$token     = ((x($arr,'token'))      ? $arr['token']   : '');

		$feed      = ((x($arr,'feed'))       ? intval($arr['feed']) : 0);

		if($ztarget) {
			$t = q("select * from hubloc where hubloc_id_url = '%s' limit 1",
				dbesc($ztarget)
			);
			if($t) {
	
				$ztarget_hash = $t[0]['hubloc_hash'];

			}
			else {
			
				// should probably perform discovery of the requestor (target) but if they actually had
				// permissions we would know about them and we only want to know who they are to 
				// enumerate their specific permissions
		
				$ztarget_hash = EMPTY_STR;
			}
		}


		$r = null;

		if(strlen($zhash)) {
			$r = q("select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
				where channel_hash = '%s' limit 1",
				dbesc($zhash)
			);
		}
		elseif(strlen($zguid) && strlen($zguid_sig)) {
			$r = q("select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
				where channel_guid = '%s' and channel_guid_sig = '%s' limit 1",
				dbesc($zguid),
				dbesc($zguid_sig)
			);
		}
		elseif(strlen($zaddr)) {
			if(strpos($zaddr,'[system]') === false) {       /* normal address lookup */
				$r = q("select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
					where ( channel_address = '%s' or xchan_addr = '%s' ) limit 1",
					dbesc($zaddr),
					dbesc($zaddr)
				);
			}

			else {

				/**
				 * The special address '[system]' will return a system channel if one has been defined,
				 * Or the first valid channel we find if there are no system channels.
				 *
				 * This is used by magic-auth if we have no prior communications with this site - and
				 * returns an identity on this site which we can use to create a valid hub record so that
				 * we can exchange signed messages. The precise identity is irrelevant. It's the hub
				 * information that we really need at the other end - and this will return it.
				 *
				 */

				$r = q("select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
					where channel_system = 1 order by channel_id limit 1");
				if(! $r) {
					$r = q("select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
						where channel_removed = 0 order by channel_id limit 1");
				}
			}
		}
		else {
			$ret['message'] = 'Invalid request';
			return($ret);
		}

		if(! $r) {
			$ret['message'] = 'Item not found.';
			return($ret);
		}

		$e = $r[0];

		$id = $e['channel_id'];

		$sys_channel     = (intval($e['channel_system'])   ? true : false);
		$special_channel = (($e['channel_pageflags'] & PAGE_PREMIUM)  ? true : false);
		$adult_channel   = (($e['channel_pageflags'] & PAGE_ADULT)    ? true : false);
		$censored        = (($e['channel_pageflags'] & PAGE_CENSORED) ? true : false);
		$searchable      = (($e['channel_pageflags'] & PAGE_HIDDEN)   ? false : true);
		$deleted         = (intval($e['xchan_deleted']) ? true : false);

		if($deleted || $censored || $sys_channel)
			$searchable = false;

		$public_forum = false;

		$role = get_pconfig($e['channel_id'],'system','permissions_role');
		if($role === 'forum' || $role === 'repository') {
			$public_forum = true;
		}
		else {
			// check if it has characteristics of a public forum based on custom permissions.
			$m = \Zotlabs\Access\Permissions::FilledAutoperms($e['channel_id']);
			if($m) {
				foreach($m as $k => $v) {
					if($k == 'tag_deliver' && intval($v) == 1)
						$ch ++;
					if($k == 'send_stream' && intval($v) == 0)
						$ch ++;
				}
				if($ch == 2)
					$public_forum = true;
			}
		}


		//  This is for birthdays and keywords, but must check access permissions
		$p = q("select * from profile where uid = %d and is_default = 1",
			intval($e['channel_id'])
		);

		$profile = array();

		if($p) {

			if(! intval($p[0]['publish']))
				$searchable = false;

			$profile['description']   = $p[0]['pdesc'];
			$profile['birthday']      = $p[0]['dob'];
			if(($profile['birthday'] != '0000-00-00') && (($bd = z_birthday($p[0]['dob'],$e['channel_timezone'])) !== ''))
				$profile['next_birthday'] = $bd;

			if($age = age($p[0]['dob'],$e['channel_timezone'],''))
				$profile['age'] = $age;
			$profile['gender']        = $p[0]['gender'];
			$profile['marital']       = $p[0]['marital'];
			$profile['sexual']        = $p[0]['sexual'];
			$profile['locale']        = $p[0]['locality'];
			$profile['region']        = $p[0]['region'];
			$profile['postcode']      = $p[0]['postal_code'];
			$profile['country']       = $p[0]['country_name'];
			$profile['about']         = $p[0]['about'];
			$profile['homepage']      = $p[0]['homepage'];
			$profile['hometown']      = $p[0]['hometown'];

			if($p[0]['keywords']) {
				$tags = array();
				$k = explode(' ',$p[0]['keywords']);
				if($k) {
					foreach($k as $kk) {
						if(trim($kk," \t\n\r\0\x0B,")) {
							$tags[] = trim($kk," \t\n\r\0\x0B,");
						}
					}
				}
				if($tags)
					$profile['keywords'] = $tags;
			}
		}

		$ret['success'] = true;

		// Communication details


		$ret['id']             = $e['xchan_guid'];
		$ret['id_sig']         = self::sign($e['xchan_guid'], $e['channel_prvkey']);
		$ret['aliases']        = [ 'acct:' . $e['xchan_addr'], $e['xchan_url'] ]; 


		$ret['public_key']     = $e['xchan_pubkey'];
		$ret['name']           = $e['xchan_name'];
		$ret['name_updated']   = $e['xchan_name_date'];
		$ret['address']        = $e['xchan_addr'];
		$ret['photo'] = [
			'url'     => $e['xchan_photo_l'],
			'type'    => $e['xchan_photo_mimetype'],
			'updated' => $e['xchan_photo_date']
		];

		$ret['channel_role'] = get_pconfig($e['channel_id'],'system','permissions_role','custom');

		$ret['url']            = $e['xchan_url'];
		$ret['connections_url']= $e['xchan_connurl'];
		$ret['follow_url']     = $e['xchan_follow'];
		$ret['target']         = $ztarget;
		$ret['searchable']     = $searchable;
		$ret['adult_content']  = $adult_channel;
		$ret['public_forum']   = $public_forum;
		if($deleted)
			$ret['deleted']        = $deleted;

		if(intval($e['channel_removed']))
			$ret['deleted_locally'] = true;



		// premium or other channel desiring some contact with potential followers before connecting.
		// This is a template - %s will be replaced with the follow_url we discover for the return channel.

		if($special_channel) {
			$ret['connect_url'] = (($e['xchan_connpage']) ? $e['xchan_connpage'] : z_root() . '/connect/' . $e['channel_address']);
		}
		// This is a template for our follow url, %s will be replaced with a webbie

		if(! $ret['follow_url'])
			$ret['follow_url'] = z_root() . '/follow?f=&url=%s';

		$permissions = get_all_perms($e['channel_id'],$ztarget_hash,false);

		if($ztarget_hash) {
			$permissions['connected'] = false;
			$b = q("select * from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
				dbesc($ztarget_hash),
				intval($e['channel_id'])
			);
			if($b)
				$permissions['connected'] = true;
		}

		if($permissions['view_profile'])
			$ret['profile']  = $profile;


		$concise_perms = [];
		if($permissions) {
			foreach($permissions as $k => $v) {
				if($v) {
					$concise_perms[] = $k;
				}
			}
			$permissions = implode(',',$concise_perms);
		}

		$ret['permissions'] = $permissions;

		// array of (verified) hubs this channel uses

		$x = self::encode_locations($e);
		if($x)
			$ret['locations'] = $x;

		$ret['site'] = self::site_info();

		call_hooks('zotinfo',$ret);

		return($ret);

	}


	static function site_info() {

		$signing_key = get_config('system','prvkey');
		$sig_method  = get_config('system','signature_algorithm','sha256');

		$ret = [];
		$ret['site'] = [];
		$ret['site']['url'] = z_root();
		$ret['site']['site_sig'] = self::sign(z_root(), $signing_key);
		$ret['site']['post'] = z_root() . '/zot';
		$ret['site']['openWebAuth']  = z_root() . '/owa';
		$ret['site']['authRedirect'] = z_root() . '/magic';
		$ret['site']['sitekey'] = get_config('system','pubkey');

		$dirmode = get_config('system','directory_mode');
		if(($dirmode === false) || ($dirmode == DIRECTORY_MODE_NORMAL))
			$ret['site']['directory_mode'] = 'normal';

		if($dirmode == DIRECTORY_MODE_PRIMARY)
			$ret['site']['directory_mode'] = 'primary';
		elseif($dirmode == DIRECTORY_MODE_SECONDARY)
			$ret['site']['directory_mode'] = 'secondary';
		elseif($dirmode == DIRECTORY_MODE_STANDALONE)
			$ret['site']['directory_mode'] = 'standalone';
		if($dirmode != DIRECTORY_MODE_NORMAL)
			$ret['site']['directory_url'] = z_root() . '/dirsearch';


		$ret['site']['encryption'] = crypto_methods();
		$ret['site']['zot'] = \Zotlabs\Lib\System::get_zot_revision();

		// hide detailed site information if you're off the grid

		if($dirmode != DIRECTORY_MODE_STANDALONE) {

			$register_policy = intval(get_config('system','register_policy'));
	
			if($register_policy == REGISTER_CLOSED)
				$ret['site']['register_policy'] = 'closed';
			if($register_policy == REGISTER_APPROVE)
				$ret['site']['register_policy'] = 'approve';
			if($register_policy == REGISTER_OPEN)
				$ret['site']['register_policy'] = 'open';


			$access_policy = intval(get_config('system','access_policy'));

			if($access_policy == ACCESS_PRIVATE)
				$ret['site']['access_policy'] = 'private';
			if($access_policy == ACCESS_PAID)
				$ret['site']['access_policy'] = 'paid';
			if($access_policy == ACCESS_FREE)
				$ret['site']['access_policy'] = 'free';
			if($access_policy == ACCESS_TIERED)
				$ret['site']['access_policy'] = 'tiered';

			$ret['site']['accounts'] = account_total();

			require_once('include/channel.php');
			$ret['site']['channels'] = channel_total();

			$ret['site']['admin'] = get_config('system','admin_email');

			$visible_plugins = array();
			if(is_array(\App::$plugins) && count(\App::$plugins)) {
				$r = q("select * from addon where hidden = 0");
				if($r)
					foreach($r as $rr)
						$visible_plugins[] = $rr['aname'];
			}

			$ret['site']['plugins']    = $visible_plugins;
			$ret['site']['sitehash']   = get_config('system','location_hash');
			$ret['site']['sitename']   = get_config('system','sitename');
			$ret['site']['sellpage']   = get_config('system','sellpage');
			$ret['site']['location']   = get_config('system','site_location');
			$ret['site']['realm']      = get_directory_realm();
			$ret['site']['project']    = \Zotlabs\Lib\System::get_platform_name() . ' ' . \Zotlabs\Lib\System::get_server_role();
			$ret['site']['version']    = \Zotlabs\Lib\System::get_project_version();

		}

		return $ret['site'];

	}


	/**
	 * @brief
	 *
	 * @param array $channel
	 * @param array $locations
	 * @param[out] array $ret
	 *   \e array \b locations result of zot_encode_locations()
	 */

	static function check_zotinfo($channel, $locations, &$ret) {

		//	logger('locations: ' . print_r($locations,true),LOGGER_DATA, LOG_DEBUG);

		// This function will likely expand as we find more things to detect and fix.
		// 1. Because magic-auth is reliant on it, ensure that the system channel has a valid hubloc
		//    Force this to be the case if anything is found to be wrong with it.

		/// @FIXME ensure that the system channel exists in the first place and has an xchan

		if($channel['channel_system']) {
			// the sys channel must have a location (hubloc)
			$valid_location = false;
			if((count($locations) === 1) && ($locations[0]['primary']) && (! $locations[0]['deleted'])) {
				if((self::verify($locations[0]['location'],$locations[0]['location_sig'],$channel['channel_pubkey']))
					&& ($locations[0]['sitekey'] === get_config('system','pubkey'))
					&& ($locations[0]['url'] === z_root()))
					$valid_location = true;
				else
					logger('sys channel: invalid url signature');
			}

			if((! $locations) || (! $valid_location)) {

				logger('System channel locations are not valid. Attempting repair.');

				// Don't trust any existing records. Just get rid of them, but only do this
				// for the sys channel as normal channels will be trickier.

				q("delete from hubloc where hubloc_hash = '%s'",
					dbesc($channel['channel_hash'])
				);

				$r = hubloc_store_lowlevel(
					[
						'hubloc_guid'     => $channel['channel_guid'],
						'hubloc_guid_sig' => $channel['channel_guid_sig'],
						'hubloc_hash'     => $channel['channel_hash'],
						'hubloc_addr'     => channel_reddress($channel),
						'hubloc_network'  => 'zot',
						'hubloc_primary'  => 1,
						'hubloc_url'      => z_root(),
						'hubloc_url_sig'  => self::sign(z_root(),$channel['channel_prvkey']),
						'hubloc_host'     => App::get_hostname(),
						'hubloc_callback' => z_root() . '/zot',
						'hubloc_sitekey'  => get_config('system','pubkey'),
						'hubloc_updated'  => datetime_convert(),
					]
				);

				if($r) {
					$x = self::encode_locations($channel);
					if($x) {
						$ret['locations'] = $x;
					}
				}
				else {
					logger('Unable to store sys hub location');
				}
			}
		}
	}

	/**
	 * @brief
	 *
	 * @param array $dr
	 * @return boolean
	 */

	static function delivery_report_is_storable($dr) {

		if(get_config('system', 'disable_dreport'))
			return false;

		/**
		 * @hooks dreport_is_storable
		 *   Called before storing a dreport record to determine whether to store it.
		 *   * \e array
		 */
		call_hooks('dreport_is_storable', $dr);

		// let plugins accept or reject - if neither, continue on
		if(array_key_exists('accept',$dr) && intval($dr['accept']))
			return true;
		if(array_key_exists('reject',$dr) && intval($dr['reject']))
			return false;

		if(! ($dr['sender']))
			return false;

		// Is the sender one of our channels?

		$c = q("select channel_id from channel where channel_hash = '%s' limit 1",
			dbesc($dr['sender'])
		);
		if(! $c)
			return false;


		// is the recipient one of our connections, or do we want to store every report?

		$r = explode(' ', $dr['recipient']);
		$rxchan = $r[0];
		$pcf = get_pconfig($c[0]['channel_id'],'system','dreport_store_all');
		if($pcf)
			return true;

		// We always add ourself as a recipient to private and relayed posts
		// So if a remote site says they can't find us, that's no big surprise
		// and just creates a lot of extra report noise

		if(($dr['location'] !== z_root()) && ($dr['sender'] === $rxchan) && ($dr['status'] === 'recipient_not_found'))
			return false;

		// If you have a private post with a recipient list, every single site is going to report
		// back a failed delivery for anybody on that list that isn't local to them. We're only
		// concerned about this if we have a local hubloc record which says we expected them to
		// have a channel on that site.

		$r = q("select hubloc_id from hubloc where hubloc_hash = '%s' and hubloc_url = '%s'",
			dbesc($rxchan),
			dbesc($dr['location'])
		);
		if((! $r) && ($dr['status'] === 'recipient_not_found'))
			return false;

		$r = q("select abook_id from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
			dbesc($rxchan),
			intval($c[0]['channel_id'])
		);
		if($r)
			return true;

		return false;
	}


	/**
	 * @brief
	 *
	 * @param array $hub
	 * @param string $sitekey (optional, default empty)
	 *
	 * @return string hubloc_url
	 */
	static function update_hub_connected($hub, $sitekey = '') {

		if($sitekey) {

			/*
			 * This hub has now been proven to be valid.
			 * Any hub with the same URL and a different sitekey cannot be valid.
			 * Get rid of them (mark them deleted). There's a good chance they were re-installs.
			 */

			q("update hubloc set hubloc_deleted = 1, hubloc_error = 1 where hubloc_url = '%s' and hubloc_sitekey != '%s' ",
				dbesc($hub['hubloc_url']),
				dbesc($sitekey)
			);

		}
		else {
			$sitekey = $hub['sitekey'];
		}

		// $sender['sitekey'] is a new addition to the protocol to distinguish
		// hublocs coming from re-installed sites. Older sites will not provide
		// this field and we have to still mark them valid, since we can't tell
		// if this hubloc has the same sitekey as the packet we received.


		// Update our DB to show when we last communicated successfully with this hub
		// This will allow us to prune dead hubs from using up resources

		$t = datetime_convert('UTC', 'UTC', 'now - 15 minutes');

		$r = q("update hubloc set hubloc_connected = '%s' where hubloc_id = %d and hubloc_sitekey = '%s' and hubloc_connected < '%s' ",
			dbesc(datetime_convert()),
			intval($hub['hubloc_id']),
			dbesc($sitekey),
			dbesc($t)
		);

		// a dead hub came back to life - reset any tombstones we might have

		if(intval($hub['hubloc_error'])) {
			q("update hubloc set hubloc_error = 0 where hubloc_id = %d and hubloc_sitekey = '%s' ",
				intval($hub['hubloc_id']),
				dbesc($sitekey)
			);
			if(intval($hub['hubloc_orphancheck'])) {
				q("update hubloc set hubloc_orphancheck = 0 where hubloc_id = %d and hubloc_sitekey = '%s' ",
					intval($hub['hubloc_id']),
					dbesc($sitekey)
				);
			}
			q("update xchan set xchan_orphan = 0 where xchan_orphan = 1 and xchan_hash = '%s'",
				dbesc($hub['hubloc_hash'])
			);
		}

		return $hub['hubloc_url'];
	}


	static function sign($data,$key,$alg = 'sha256') {
		if(! $key)
			return 'no key';
		$sig = '';
		openssl_sign($data,$sig,$key,$alg);
		return $alg . '.' . base64url_encode($sig);
	}

	static function verify($data,$sig,$key) {

		if(! $key)
			return false;

		$verify = 0;

		$separator = strpos($sig,'.');

		if($separator) {
			$alg = substr($sig,0,$separator);
			$signature = base64url_decode(substr($sig,$separator+1));
	
			$verify = @openssl_verify($data,$signature,$key,$alg);

			if($verify === (-1)) {
				while($msg = openssl_error_string())
					logger('openssl_verify: ' . $msg,LOGGER_NORMAL,LOG_ERR);
				btlogger('openssl_verify: key: ' . $key, LOGGER_DEBUG, LOG_ERR); 
			}
		}
		return (($verify > 0) ? true : false);
	}



	static function is_zot_request() {

		if($_REQUEST['module_format'] === 'json')
			return true;

		$x = getBestSupportedMimeType([
			'application/x-zot+json'
		]);

		return(($x) ? true : false);

	}

}

