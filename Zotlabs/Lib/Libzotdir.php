<?php

namespace Zotlabs\Lib;

use Zotlabs\Lib\Libzot;

require_once('include/permissions.php');


class Libzotdir {

	/**
	 * @brief
	 *
	 * @param int $dirmode
	 * @return array
	 */

	static function find_upstream_directory($dirmode) {
		global $DIRECTORY_FALLBACK_SERVERS;

		$preferred = get_config('system','directory_server');

		// Thwart attempts to use a private directory

		if(($preferred) && ($preferred != z_root())) {
			$r = q("select * from site where site_url = '%s' limit 1",
				dbesc($preferred)
			);
			if(($r) && ($r[0]['site_flags'] & DIRECTORY_MODE_STANDALONE)) {
				$preferred = '';
			}		
		}


		if (! $preferred) {

			/*
			 * No directory has yet been set. For most sites, pick one at random
			 * from our list of directory servers. However, if we're a directory
			 * server ourself, point at the local instance
			 * We will then set this value so this should only ever happen once.
			 * Ideally there will be an admin setting to change to a different 
			 * directory server if you don't like our choice or if circumstances change.
			 */

			$dirmode = intval(get_config('system','directory_mode'));
			if ($dirmode == DIRECTORY_MODE_NORMAL) {
				$toss = mt_rand(0,count($DIRECTORY_FALLBACK_SERVERS));
				$preferred = $DIRECTORY_FALLBACK_SERVERS[$toss];
				if(! $preferred) {
					$preferred = DIRECTORY_FALLBACK_MASTER;
				}
				set_config('system','directory_server',$preferred);
			} 
			else {
				set_config('system','directory_server',z_root());
			}
		}
		if($preferred) {
			return [ 'url' => $preferred ];
		}
		else {
			return [];
		}
	}


	/**
	 * Directories may come and go over time. We will need to check that our
	 * directory server is still valid occasionally, and reset to something that
	 * is if our directory has gone offline for any reason
	 */

	static function check_upstream_directory() {

		$directory = get_config('system', 'directory_server');

		// it's possible there is no directory server configured and the local hub is being used.
		// If so, default to preserving the absence of a specific server setting.

		$isadir = true;

		if ($directory) {
			$j = Zotfinger::exec($directory);
			if(array_path_exists('data/directory_mode',$j)) {
				if ($j['data']['directory_mode'] === 'normal') {
					$isadir = false;
				}
			}
		}

		if (! $isadir)
			set_config('system', 'directory_server', '');
	}


	static function get_directory_setting($observer, $setting) {

		if ($observer)
			$ret = get_xconfig($observer, 'directory', $setting);
		else
			$ret = ((array_key_exists($setting,$_SESSION)) ? intval($_SESSION[$setting]) : false);

		if($ret === false)
			$ret = get_config('directory', $setting);


		// 'safemode' is the default if there is no observer or no established preference. 

		if($setting === 'safemode' && $ret === false)
			$ret = 1;

		if($setting === 'globaldir' && intval(get_config('system','localdir_hide')))
			$ret = 1;

		return $ret;
	}

	/**
	 * @brief Called by the directory_sort widget.
	 */
	static function dir_sort_links() {

		$safe_mode = 1;

		$observer = get_observer_hash();

		$safe_mode = self::get_directory_setting($observer, 'safemode');
		$globaldir = self::get_directory_setting($observer, 'globaldir');
		$pubforums = self::get_directory_setting($observer, 'pubforums');

		$hide_local = intval(get_config('system','localdir_hide'));
		if($hide_local)
			$globaldir = 1;


		// Build urls without order and pubforums so it's easy to tack on the changed value
		// Probably there's an easier way to do this

		$directory_sort_order = get_config('system','directory_sort_order');
		if(! $directory_sort_order)
			$directory_sort_order = 'date';

		$current_order = (($_REQUEST['order']) ? $_REQUEST['order'] : $directory_sort_order);
		$suggest = (($_REQUEST['suggest']) ? '&suggest=' . $_REQUEST['suggest'] : '');

		$url = 'directory?f=';

		$tmp = array_merge($_GET,$_POST);
		unset($tmp['suggest']);
		unset($tmp['pubforums']);
		unset($tmp['global']);
		unset($tmp['safe']);
		unset($tmp['q']);
		unset($tmp['f']);
		$forumsurl = $url . http_build_query($tmp) . $suggest;

		$o = replace_macros(get_markup_template('dir_sort_links.tpl'), [
			'$header'    => t('Directory Options'),
			'$forumsurl' => $forumsurl,
			'$safemode'  => array('safemode', t('Safe Mode'),$safe_mode,'',array(t('No'), t('Yes')),' onchange=\'window.location.href="' . $forumsurl . '&safe="+(this.checked ? 1 : 0)\''),
			'$pubforums' => array('pubforums', t('Public Forums Only'),$pubforums,'',array(t('No'), t('Yes')),' onchange=\'window.location.href="' . $forumsurl . '&pubforums="+(this.checked ? 1 : 0)\''),
			'$hide_local' => $hide_local,
			'$globaldir' => array('globaldir', t('This Website Only'), 1-intval($globaldir),'',array(t('No'), t('Yes')),' onchange=\'window.location.href="' . $forumsurl . '&global="+(this.checked ? 0 : 1)\''),
		]);

		return $o;
	}

	/**
	 * @brief Checks the directory mode of this hub.
	 *
	 * Checks the directory mode of this hub to see if it is some form of directory server. If it is,
	 * get the directory realm of this hub. Fetch a list of all other directory servers in this realm and request
	 * a directory sync packet. This will contain both directory updates and new ratings. Store these all in the DB. 
	 * In the case of updates, we will query each of them asynchronously from a poller task. Ratings are stored 
	 * directly if the rater's signature matches.
	 *
	 * @param int $dirmode;
	 */

	static function sync_directories($dirmode) {

		if ($dirmode == DIRECTORY_MODE_STANDALONE || $dirmode == DIRECTORY_MODE_NORMAL)
			return;

		$realm = get_directory_realm();
		if ($realm == DIRECTORY_REALM) {
			$r = q("select * from site where (site_flags & %d) > 0 and site_url != '%s' and site_type = %d and ( site_realm = '%s' or site_realm = '') ",
				intval(DIRECTORY_MODE_PRIMARY|DIRECTORY_MODE_SECONDARY),
				dbesc(z_root()),
				intval(SITE_TYPE_ZOT),
				dbesc($realm)
			);
		} 
		else {
			$r = q("select * from site where (site_flags & %d) > 0 and site_url != '%s' and site_realm like '%s' and site_type = %d ",
				intval(DIRECTORY_MODE_PRIMARY|DIRECTORY_MODE_SECONDARY),
				dbesc(z_root()),
				dbesc(protect_sprintf('%' . $realm . '%')),
				intval(SITE_TYPE_ZOT)
			);
		}

		// If there are no directory servers, setup the fallback master
		/** @FIXME What to do if we're in a different realm? */

		if ((! $r) && (z_root() != DIRECTORY_FALLBACK_MASTER)) {

			$x = site_store_lowlevel(
				[
					'site_url'       => DIRECTORY_FALLBACK_MASTER,
					'site_flags'     => DIRECTORY_MODE_PRIMARY,
					'site_update'    => NULL_DATE, 
					'site_directory' => DIRECTORY_FALLBACK_MASTER . '/dirsearch',
					'site_realm'     => DIRECTORY_REALM,
					'site_valid'     => 1,
				]
			);

			$r = q("select * from site where site_flags in (%d, %d) and site_url != '%s' and site_type = %d ",
				intval(DIRECTORY_MODE_PRIMARY),
				intval(DIRECTORY_MODE_SECONDARY),
				dbesc(z_root()),
				intval(SITE_TYPE_ZOT)
			);
		}
		if (! $r)
			return;

		foreach ($r as $rr) {
			if (! $rr['site_directory'])
				continue;

			logger('sync directories: ' . $rr['site_directory']);

			// for brand new directory servers, only load the last couple of days.
			// It will take about a month for a new directory to obtain the full current repertoire of channels.
			/** @FIXME Go back and pick up earlier ratings if this is a new directory server. These do not get refreshed. */

			$token = get_config('system','realm_token');

			$syncdate = (($rr['site_sync'] <= NULL_DATE) ? datetime_convert('UTC','UTC','now - 2 days') : $rr['site_sync']);
			$x = z_fetch_url($rr['site_directory'] . '?f=&sync=' . urlencode($syncdate) . (($token) ? '&t=' . $token : ''));

			if (! $x['success'])
				continue;

			$j = json_decode($x['body'],true);
			if (!($j['transactions']) || ($j['ratings']))
				continue;

			q("update site set site_sync = '%s' where site_url = '%s'",
				dbesc(datetime_convert()),
				dbesc($rr['site_url'])
			);

			logger('sync_directories: ' . $rr['site_url'] . ': ' . print_r($j,true), LOGGER_DATA);

			if (is_array($j['transactions']) && count($j['transactions'])) {
				foreach ($j['transactions'] as $t) {
					$r = q("select * from updates where ud_guid = '%s' limit 1",
						dbesc($t['transaction_id'])
					);
					if($r)
						continue;

					$ud_flags = 0;
					if (is_array($t['flags']) && in_array('deleted',$t['flags']))
						$ud_flags |= UPDATE_FLAGS_DELETED;
					if (is_array($t['flags']) && in_array('forced',$t['flags']))
						$ud_flags |= UPDATE_FLAGS_FORCED;
	
					$z = q("insert into updates ( ud_hash, ud_guid, ud_date, ud_flags, ud_addr )
						values ( '%s', '%s', '%s', %d, '%s' ) ",
						dbesc($t['hash']),
						dbesc($t['transaction_id']),
						dbesc($t['timestamp']),
						intval($ud_flags),
						dbesc($t['address'])
					);
				}
			}
		}
	}



	/**
	 * @brief
	 *
	 * Given an update record, probe the channel, grab a zot-info packet and refresh/sync the data.
	 *
	 * Ignore updating records marked as deleted.
	 *
	 * If successful, sets ud_last in the DB to the current datetime for this
	 * reddress/webbie.
	 *
	 * @param array $ud Entry from update table
	 */

	static function update_directory_entry($ud) {

		logger('update_directory_entry: ' . print_r($ud,true), LOGGER_DATA);

		if ($ud['ud_addr'] && (! ($ud['ud_flags'] & UPDATE_FLAGS_DELETED))) {
			$success = false;

			$href = \Zotlabs\Lib\Webfinger::zot_url(punify($url));
			if($href) {
				$zf = \Zotlabs\Lib\Zotfinger::exec($href);
			}
			if(is_array($zf) && array_path_exists('signature/signer',$zf) && $zf['signature']['signer'] === $href && intval($zf['signature']['header_valid'])) {
				$xc = Libzot::import_xchan($zf['data'], 0, $ud);
			}
			else {
				q("update updates set ud_last = '%s' where ud_addr = '%s'",
					dbesc(datetime_convert()),
					dbesc($ud['ud_addr'])
				);
			}
		}
	}


	/**
	 * @brief Push local channel updates to a local directory server.
	 *
	 * This is called from include/directory.php if a profile is to be pushed to the
	 * directory and the local hub in this case is any kind of directory server.
	 *
	 * @param int $uid
	 * @param boolean $force
	 */

	static function local_dir_update($uid, $force) {

	
		logger('local_dir_update: uid: ' . $uid, LOGGER_DEBUG);

		$p = q("select channel.channel_hash, channel_address, channel_timezone, profile.* from profile left join channel on channel_id = uid where uid = %d and is_default = 1",
			intval($uid)
		);

		$profile = array();
		$profile['encoding'] = 'zot';

		if ($p) {
			$hash = $p[0]['channel_hash'];

			$profile['description'] = $p[0]['pdesc'];
			$profile['birthday']    = $p[0]['dob'];
			if ($age = age($p[0]['dob'],$p[0]['channel_timezone'],''))  
				$profile['age'] = $age;

			$profile['gender']      = $p[0]['gender'];
			$profile['marital']     = $p[0]['marital'];
			$profile['sexual']      = $p[0]['sexual'];
			$profile['locale']      = $p[0]['locality'];
			$profile['region']      = $p[0]['region'];
			$profile['postcode']    = $p[0]['postal_code'];
			$profile['country']     = $p[0]['country_name'];
			$profile['about']       = $p[0]['about'];
			$profile['homepage']    = $p[0]['homepage'];
			$profile['hometown']    = $p[0]['hometown'];

			if ($p[0]['keywords']) {
				$tags = array();
				$k = explode(' ', $p[0]['keywords']);
				if ($k)
					foreach ($k as $kk)
						if (trim($kk))
							$tags[] = trim($kk);

				if ($tags)
					$profile['keywords'] = $tags;
			}

			$hidden = (1 - intval($p[0]['publish']));

			logger('hidden: ' . $hidden);

			$r = q("select xchan_hidden from xchan where xchan_hash = '%s' limit 1",
				dbesc($p[0]['channel_hash'])
			);

			if(intval($r[0]['xchan_hidden']) != $hidden) {
				$r = q("update xchan set xchan_hidden = %d where xchan_hash = '%s'",
					intval($hidden),
					dbesc($p[0]['channel_hash'])
				);
			}

			$arr = [ 'channel_id' => $uid, 'hash' => $hash, 'profile' => $profile ];
			call_hooks('local_dir_update', $arr);

			$address = channel_reddress($p[0]);

			if (perm_is_allowed($uid, '', 'view_profile')) {
				self::import_directory_profile($hash, $arr['profile'], $address, 0);
			}
			else {
				// they may have made it private
				$r = q("delete from xprof where xprof_hash = '%s'",
					dbesc($hash)
				);
				$r = q("delete from xtag where xtag_hash = '%s'",
					dbesc($hash)
				);
			}
	
		}

		$ud_hash = random_string() . '@' . \App::get_hostname();
		self::update_modtime($hash, $ud_hash, channel_reddress($p[0]),(($force) ? UPDATE_FLAGS_FORCED : UPDATE_FLAGS_UPDATED));
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
			self::update_modtime($arr['xprof_hash'],random_string() . '@' . \App::get_hostname(), $addr, $ud_flags);

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






}