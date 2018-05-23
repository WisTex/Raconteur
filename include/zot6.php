<?php


function zot_sign($data,$key,$alg = 'sha256') {
	if(! $key)
		return 'no key';
	$sig = '';
	openssl_sign($data,$sig,$key,$alg);
	return $alg . '.' . base64url_encode($sig);
}

function zot_verify($data,$sig,$key) {

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



function zotvi_is_zot_request() {

	if($_REQUEST['module_format'] === 'json')
		return true;

	$x = getBestSupportedMimeType([
		'application/x-zot+json'
	]);

	return(($x) ? true : false);

}


class zot6 {

	static function zotinfo($arr) {

		$ret = [];


	$zhash     = ((x($arr,'guid_hash'))  ? $arr['guid_hash']   : '');
	$zguid     = ((x($arr,'guid'))       ? $arr['guid']        : '');
	$zguid_sig = ((x($arr,'guid_sig'))   ? $arr['guid_sig']    : '');
	$zaddr     = ((x($arr,'address'))    ? $arr['address']     : '');
	$ztarget   = ((x($arr,'target'))     ? $arr['target']      : '');
	$zsig      = ((x($arr,'target_sig')) ? $arr['target_sig']  : '');
	$zkey      = ((x($arr,'key'))        ? $arr['key']         : '');
	$mindate   = ((x($arr,'mindate'))    ? $arr['mindate']     : '');
	$token     = ((x($arr,'token'))      ? $arr['token']   : '');

	$feed      = ((x($arr,'feed'))       ? intval($arr['feed']) : 0);

	if($ztarget) {
		if((! $zkey) || (! $zsig) || (! rsa_verify($ztarget,base64url_decode($zsig),$zkey))) {
			logger('zfinger: invalid target signature');
			$ret['message'] = t("invalid target signature");
			return($ret);
		}
	}

	$ztarget_hash = (($ztarget && $zsig) ? make_xchan_hash($ztarget,$zsig) : '' );

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


	$ret['guid']           = $e['xchan_guid'];
	$ret['guid_sig']       = zot_sign($e['xchan_guid'], $e['channel_prvkey']);
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
	$ret['connections_url']= (($e['xchan_connurl']) ? $e['xchan_connurl'] : z_root() . '/poco/' . $e['channel_address']);
	$ret['follow_url']     = $e['xchan_follow'];
	$ret['target']         = $ztarget;
	$ret['target_sig']     = $zsig;
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

	// encrypt this with the default aes256cbc since we cannot be sure at this point which
	// algorithms are preferred for communications with the remote site; notably
	// because ztarget refers to an xchan and we don't necessarily know the origination
	// location.

	$ret['permissions'] = (($ztarget && $zkey) ? crypto_encapsulate(json_encode($permissions),$zkey) : $permissions);


	// array of (verified) hubs this channel uses

	$x = zot6::zot_encode_locations($e);
	if($x)
		$ret['locations'] = $x;

	$ret['site'] = zot6::zot_site_info();


	call_hooks('zot6_finger',$ret);
	return($ret);

}

/**
 * @brief Returns an array with all known distinct hubs for this channel.
 *
 * @see zot_get_hublocs()
 * @param array $channel an associative array which must contain
 *  * \e string \b channel_hash the hash of the channel
 * @return array an array with associative arrays
 */
static function zot_encode_locations($channel) {
	$ret = array();

	$x = zot_get_hublocs($channel['channel_hash']);

	if($x && count($x)) {
		foreach($x as $hub) {

			// if this is a local channel that has been deleted, the hubloc is no good - make sure it is marked deleted
			// so that nobody tries to use it.

			if($hub['hubloc_url'] === z_root()) {
				if(intval($channel['channel_removed']))
					$hub['hubloc_deleted'] = 1;
				$hub['hubloc_callback'] = z_root() . '/zot';
			}

			$ret[] = [
				'host'     => $hub['hubloc_host'],
				'address'  => $hub['hubloc_addr'],
				'primary'  => (intval($hub['hubloc_primary']) ? true : false),
				'url'      => $hub['hubloc_url'],
				'url_sig'  => zot_sign($hub['hubloc_url'],$channel['channel_prvkey']),
				'callback' => $hub['hubloc_callback'],
				'sitekey'  => $hub['hubloc_sitekey'],
				'deleted'  => (intval($hub['hubloc_deleted']) ? true : false)
			];
		}
	}

	return $ret;
}

static function zot_site_info() {

	$signing_key = get_config('system','prvkey');
	$sig_method  = get_config('system','signature_algorithm','sha256');

	$ret = [];
	$ret['site'] = [];
	$ret['site']['url'] = z_root();
	$ret['site']['site_sig'] = zot_sign(z_root(), $signing_key);
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
	$ret['site']['zot'] = Zotlabs\Lib\System::get_zot_revision();

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
		if(is_array(App::$plugins) && count(App::$plugins)) {
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
		$ret['site']['project']    = Zotlabs\Lib\System::get_platform_name() . ' ' . Zotlabs\Lib\System::get_server_role();
		$ret['site']['version']    = Zotlabs\Lib\System::get_project_version();

	}

	return $ret['site'];

}

}