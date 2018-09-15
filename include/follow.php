<?php /** @file */


use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Group;

//
// Takes a $uid and the channel associated with the uid, and a url/handle and adds a new channel

// Returns an array
//  $return['success'] boolean true if successful
//  $return['abook'] Address book entry joined with xchan if successful
//  $return['message'] error text if success is false.



function new_contact($uid,$url,$channel,$interactive = false, $confirm = false) {

	$result = [ 'success' => false, 'message' => '' ];

	$my_perms = false;
	$is_zot   = false;
	$protocol = '';

	if(substr($url,0,1) === '[') {
		$x = strpos($url,']');
		if($x) {
			$protocol = substr($url,1,$x-1);
			$url = substr($url,$x+1);
		}
	}

	$url = rtrim($url,'/');

	if(! allowed_url($url)) {
		$result['message'] = t('Channel is blocked on this site.');
		return $result;
	}

	if(! $url) {
		$result['message'] = t('Channel location missing.');
		return $result;
	}

	// check service class limits

	$r = q("select count(*) as total from abook where abook_channel = %d and abook_self = 0 ",
		intval($uid)
	);
	if($r)
		$total_channels = $r[0]['total'];

	if(! service_class_allows($uid,'total_channels',$total_channels)) {
		$result['message'] = upgrade_message();
		return $result;
	}

	$xchan_hash = '';
	$sql_options = (($protocol) ? " and xchan_network = '" . dbesc($protocol) . "' " : '');
		
	$r = q("select * from xchan where xchan_hash = '%s' or xchan_url = '%s' or xchan_addr = '%s' $sql_options limit 1",
		dbesc($url),
		dbesc($url),
		dbesc($url)
	);

	$singleton = false;
	$d = false;

	if(! $r) {

		// try RSS discovery

		$wf = discover_by_webbie($url,$protocol);

		if(! $wf) {
			$feeds = get_config('system','feed_contacts');

			if(($feeds) && ($protocol === '' || $protocol === 'feed' || $protocol === 'rss')) {
				$d = discover_feed($url);
			}
			else {
				$result['message'] = t('Remote channel or protocol unavailable.');
				return $result;
			}
		}
	}

	if($wf || $d) {
		$r = q("select * from xchan where xchan_hash = '%s' or xchan_url = '%s' or xchan_addr = '%s' limit 1",
			dbesc(($wf) ? $wf : $url),
			dbesc($url),
			dbesc($url)
		);
	}

	// if discovery was a success we should have an xchan record in $r

	if($r) {
		$xchan = $r[0];
		$xchan_hash = $r[0]['xchan_hash'];
		$their_perms = EMPTY_STR;
	}

	if(! $xchan_hash) {
		$result['message'] = t('Channel discovery failed.');
		logger('follow: ' . $result['message']);
		return $result;
	}

	if($r[0]['xchan_network'] === 'activitypub') {
		$singleton = 1;
	}

	$aid = $channel['channel_account_id'];
	$hash = $channel['channel_hash'];
	$default_group = $channel['channel_default_group'];

	if($hash == $xchan_hash) {
		$result['message'] = t('Cannot connect to yourself.');
		return $result;
	}

	if($xchan['xchan_network'] === 'rss') {

		// check service class feed limits

		$r = q("select count(*) as total from abook where abook_account = %d and abook_feed = 1 ",
			intval($aid)
		);
		if($r)
			$total_feeds = $r[0]['total'];

		if(! service_class_allows($uid,'total_feeds',$total_feeds)) {
			$result['message'] = upgrade_message();
			return $result;
		}

		// Always set these "remote" permissions for feeds since we cannot interact with them
		// to negotiate a suitable permission response

		$p = get_abconfig($uid,$xchan_hash,'system','their_perms',EMPTY_STR);
		if($p)
			$p .= ',';
		$p .= 'view_stream,republish';
		set_abconfig($uid,$xchan_hash,'system','their_perms',$p);

	}

	$p = \Zotlabs\Access\Permissions::connect_perms($uid);
	$my_perms = \Zotlabs\Access\Permissions::serialise($p['perms']);

	$profile_assign = get_pconfig($uid,'system','profile_assign','');


	$r = q("select abook_id, abook_xchan, abook_pending, abook_instance from abook 
		where abook_xchan = '%s' and abook_channel = %d limit 1",
		dbesc($xchan_hash),
		intval($uid)
	);

	if($r) {

		$abook_instance = $r[0]['abook_instance'];

		if(($singleton) && strpos($abook_instance,z_root()) === false) {
			if($abook_instance)
				$abook_instance .= ',';
			$abook_instance .= z_root();

			$x = q("update abook set abook_instance = '%s', abook_not_here = 0 where abook_id = %d",
				dbesc($abook_instance),
				intval($r[0]['abook_id'])
			);
		}

		if(intval($r[0]['abook_pending'])) {
			$x = q("update abook set abook_pending = 0 where abook_id = %d",
				intval($r[0]['abook_id'])
			);
		}
	}
	else {
		$closeness = get_pconfig($uid,'system','new_abook_closeness');
		if($closeness === false)
			$closeness = 80;

		$r = abook_store_lowlevel(
			[
				'abook_account'   => intval($aid),
				'abook_channel'   => intval($uid),
				'abook_closeness' => intval($closeness),
				'abook_xchan'     => $xchan_hash,
				'abook_profile'   => $profile_assign,
				'abook_feed'      => intval(($xchan['xchan_network'] === 'rss') ? 1 : 0),
				'abook_created'   => datetime_convert(),
				'abook_updated'   => datetime_convert(),
				'abook_instance'  => (($singleton) ? z_root() : '')
			]
		);
	}

	if(! $r)
		logger('abook creation failed');

	if($my_perms) {
		set_abconfig($uid,$xchan_hash,'system','my_perms',$my_perms);
	}

	$r = q("select abook.*, xchan.* from abook left join xchan on abook_xchan = xchan_hash 
		where abook_xchan = '%s' and abook_channel = %d limit 1",
		dbesc($xchan_hash),
		intval($uid)
	);

	if($r) {
		$result['abook'] = $r[0];
		Zotlabs\Daemon\Master::Summon([ 'Notifier', 'permissions_create', $result['abook']['abook_id'] ]);
	}

	$arr = [ 'channel_id' => $uid, 'channel' => $channel, 'abook' => $result['abook'] ];

	call_hooks('follow', $arr);

	/** If there is a default group for this channel, add this connection to it */

	if($default_group) {
		$g = Group::rec_byhash($uid,$default_group);
		if($g) {
			Group::member_add($uid,'',$xchan_hash,$g['id']);
		}
	}

	$result['success'] = true;
	return $result;
}
