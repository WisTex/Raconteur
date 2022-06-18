<?php

namespace Code\Lib;

use App;
use Code\Lib\Libzot;
use Code\Lib\Queue;
use Code\Lib\Channel;    
use Code\Lib\Connect;
use Code\Lib\ServiceClass;    
use Code\Lib\DReport;
use Code\Daemon\Run;
use Code\Extend\Hook;

class Libsync
{

    /**
     * @brief Builds and sends a sync packet.
     *
     * Send a zot packet to all hubs where this channel is duplicated, refreshing
     * such things as personal settings, channel permissions, address book updates, etc.
     *
     * By default, sync the channel and any pconfig changes which were made in the current process
     * AccessLists (aka privacy groups) will also be included if $groups_changed is true.
     * To include other data sources, provide them as $packet.
     *
     * @param int $uid (optional) default 0
     * @param array $packet (optional) default null
     * @param bool $groups_changed (optional) default false
     */

    public static function build_sync_packet($uid = 0, $packet = null, $groups_changed = false)
    {

        // logger('build_sync_packet');

        $keychange = (($packet && array_key_exists('keychange', $packet)) ? true : false);
        if ($keychange) {
            logger('keychange sync');
        }

        if (!$uid) {
            $uid = local_channel();
        }

        if (!$uid) {
            return;
        }

        $channel = Channel::from_id($uid);
        if (!$channel) {
            return;
        }

        // don't provide these in the export

        unset($channel['channel_active']);
        unset($channel['channel_password']);
        unset($channel['channel_salt']);

		$h = q("select hubloc.*, site.site_crypto from hubloc left join site on site_url = hubloc_url 
			where hubloc_hash = '%s' and hubloc_network in ('nomad','zot6') and hubloc_deleted = 0",
			dbesc(($keychange) ? $packet['keychange']['old_hash'] : $channel['channel_hash'])
		);

        if (!$h) {
            return;
        }

        $synchubs = [];

        foreach ($h as $x) {
            if ($x['hubloc_host'] == App::get_hostname()) {
                continue;
            }

            $y = q(
                "select site_dead from site where site_url = '%s' limit 1",
                dbesc($x['hubloc_url'])
            );

            if ((!$y) || ($y[0]['site_dead'] == 0)) {
                $synchubs[] = $x;
            }
        }

        if (!$synchubs) {
            return;
        }

        $env_recips = [$channel['channel_hash']];

        if ($packet) {
            logger('packet: ' . print_r($packet, true), LOGGER_DATA, LOG_DEBUG);
        }

        $info = (($packet) ? $packet : []);
        $info['type'] = 'sync';
        $info['encoding'] = 'red'; // note: not zot, this packet is very platform specific
        $info['relocate'] = ['channel_address' => $channel['channel_address'], 'url' => z_root()];
        $info['schema'] = 'streams';
    
        if (array_key_exists($uid, App::$config) && array_key_exists('transient', App::$config[$uid])) {
            $settings = App::$config[$uid]['transient'];
            if ($settings) {
                $info['config'] = $settings;
            }
        }

        if ($channel) {
            $info['channel'] = [];
            foreach ($channel as $k => $v) {
                // filter out any joined tables like xchan

                if (strpos($k, 'channel_') !== 0) {
                    continue;
                }

                // don't pass these elements, they should not be synchronised


                $disallowed = ['channel_id', 'channel_account_id', 'channel_primary', 'channel_address',
                    'channel_deleted', 'channel_removed', 'channel_system'];

                if (!$keychange) {
                    $disallowed[] = 'channel_prvkey';
                }

                if (in_array($k, $disallowed)) {
                    continue;
                }

                $info['channel'][$k] = $v;
            }
        }

        if ($groups_changed) {
            $r = q(
                "select hash as collection, visible, deleted, rule, gname as name from pgrp where uid = %d ",
                intval($uid)
            );
            if ($r) {
                $info['collections'] = $r;
            }

            $r = q(
                "select pgrp.hash as collection, pgrp_member.xchan as member from pgrp left join pgrp_member on pgrp.id = pgrp_member.gid 
				where pgrp_member.uid = %d ",
                intval($uid)
            );
            if ($r) {
                $info['collection_members'] = $r;
            }
        }

        $interval = get_config('system', 'delivery_interval', 2);

        logger('Packet: ' . print_r($info, true), LOGGER_DATA, LOG_DEBUG);

        $total = count($synchubs);

        foreach ($synchubs as $hub) {
            $hash = random_string();
            $n = Libzot::build_packet($channel, 'sync', $env_recips, json_encode($info), 'red', $hub['hubloc_sitekey'], $hub['site_crypto']);
            Queue::insert([
                'hash' => $hash,
                'account_id' => $channel['channel_account_id'],
                'channel_id' => $channel['channel_id'],
                'posturl' => $hub['hubloc_callback'],
                'notify' => $n,
                'msg' => EMPTY_STR
            ]);


            $x = q("select count(outq_hash) as total from outq where outq_delivered = 0");
            if (intval($x[0]['total']) > intval(get_config('system', 'force_queue_threshold', 3000))) {
                logger('immediate delivery deferred.', LOGGER_DEBUG, LOG_INFO);
                Queue::update($hash);
                continue;
            }


            Run::Summon(['Deliver', $hash]);
            $total = $total - 1;

            if ($interval && $total) {
                @time_sleep_until(microtime(true) + (float)$interval);
            }
        }
    }


    public static function build_link_packet($uid = 0, $packet = null)
    {

        // logger('build_link_packet');

        if (!$uid) {
            $uid = local_channel();
        }

        if (!$uid) {
            return;
        }

        $channel = Channel::from_id($uid);
        if (!$channel) {
            return;
        }

        $l = q(
            "select link from linkid where ident = '%s' and sigtype = 2",
            dbesc($channel['channel_hash'])
        );

        if (!$l) {
            return;
        }

        $hashes = ids_to_querystr($l, 'link', true);

		$h = q("select hubloc.*, site.site_crypto from hubloc left join site on site_url = hubloc_url where hubloc_hash in (" . protect_sprintf($hashes) . ") and hubloc_network in ('nomad','zot6') and hubloc_deleted = 0");

        if (!$h) {
            return;
        }

        $interval = get_config('system', 'delivery_interval', 2);


        foreach ($h as $x) {
            if ($x['hubloc_host'] == App::get_hostname()) {
                continue;
            }

            $y = q(
                "select site_dead from site where site_url = '%s' limit 1",
                dbesc($x['hubloc_url'])
            );

            if (($y) && (intval($y[0]['site_dead']) == 1)) {
                $continue;
            }

            $env_recips = [$x['hubloc_hash']];

            if ($packet) {
                logger('packet: ' . print_r($packet, true), LOGGER_DATA, LOG_DEBUG);
            }

            $info = (($packet) ? $packet : []);
            $info['type'] = 'sync';
            $info['encoding'] = 'red'; // note: not zot, this packet is very platform specific

            logger('Packet: ' . print_r($info, true), LOGGER_DATA, LOG_DEBUG);


            $hash = random_string();
            $n = Libzot::build_packet($channel, 'sync', $env_recips, json_encode($info), 'red', $x['hubloc_sitekey'], $x['site_crypto']);
            Queue::insert([
                'hash' => $hash,
                'account_id' => $channel['channel_account_id'],
                'channel_id' => $channel['channel_id'],
                'posturl' => $x['hubloc_callback'],
                'notify' => $n,
                'msg' => EMPTY_STR
            ]);

            $y = q("select count(outq_hash) as total from outq where outq_delivered = 0");
            if (intval($y[0]['total']) > intval(get_config('system', 'force_queue_threshold', 3000))) {
                logger('immediate delivery deferred.', LOGGER_DEBUG, LOG_INFO);
                Queue::update($hash);
                continue;
            }

            Run::Summon(['Deliver', $hash]);

            if ($interval && count($h) > 1) {
                @time_sleep_until(microtime(true) + (float)$interval);
            }
        }
    }


    /**
     * @brief
     *
     * @param array $sender
     * @param array $arr
     * @param array $deliveries
     * @return array
     */

    public static function process_channel_sync_delivery($sender, $arr, $deliveries)
    {

        require_once('include/import.php');

        $result = [];

        $schema = (isset($arr['schema']) && $arr['schema']) ? $arr['schema'] : 'unknown';
        $keychange = ((array_key_exists('keychange', $arr)) ? true : false);

        foreach ($deliveries as $d) {
            $linked_channel = false;

            $r = q(
                "select * from channel where channel_hash = '%s' limit 1",
                dbesc($sender)
            );

            $DR = new DReport(z_root(), $sender, $d, 'sync');

            if (!$r) {
                $l = q(
                    "select ident from linkid where link = '%s' and sigtype = 2 limit 1",
                    dbesc($sender)
                );
                if ($l) {
                    $linked_channel = true;
                    $r = q(
                        "select * from channel where channel_hash = '%s' limit 1",
                        dbesc($l[0]['ident'])
                    );
                }
            }

            if (!$r) {
                $DR->update('recipient not found');
                $result[] = $DR->get();
                continue;
            }

            $channel = $r[0];

            $DR->set_name($channel['channel_name'] . ' <' . Channel::get_webfinger($channel) . '>');

            $max_friends = ServiceClass::fetch($channel['channel_id'], 'total_channels');
            $max_feeds = ServiceClass::account_fetch($channel['channel_account_id'], 'total_feeds');

            if ($channel['channel_hash'] != $sender && (!$linked_channel)) {
                logger('Possible forgery. Sender ' . $sender . ' is not ' . $channel['channel_hash']);
                $DR->update('channel mismatch');
                $result[] = $DR->get();
                continue;
            }

            if ($keychange) {
                self::keychange($channel, $arr);
                continue;
            }

            // if the clone is active, so are we

            if (substr($channel['channel_active'], 0, 10) !== substr(datetime_convert(), 0, 10)) {
                q(
                    "UPDATE channel set channel_active = '%s' where channel_id = %d",
                    dbesc(datetime_convert()),
                    intval($channel['channel_id'])
                );
            }

            if (array_key_exists('config', $arr) && is_array($arr['config']) && count($arr['config'])) {
                foreach ($arr['config'] as $cat => $k) {
                    foreach ($arr['config'][$cat] as $k => $v) {
                        set_pconfig($channel['channel_id'], $cat, $k, $v);
                    }
                }
            }

            if (array_key_exists('atoken', $arr) && $arr['atoken']) {
                sync_atoken($channel, $arr['atoken']);
            }

            if (array_key_exists('xign', $arr) && $arr['xign']) {
                sync_xign($channel, $arr['xign']);
            }

            if (array_key_exists('block_xchan', $arr) && $arr['block_xchan']) {
                import_xchans($arr['block_xchan']);
            }

            if (array_key_exists('block', $arr) && $arr['block']) {
                sync_block($channel, $arr['block']);
            }

            if (array_key_exists('obj', $arr) && $arr['obj']) {
                sync_objs($channel, $arr['obj']);
            }

            if (array_key_exists('likes', $arr) && $arr['likes']) {
                import_likes($channel, $arr['likes']);
            }

            if (array_key_exists('app', $arr) && $arr['app']) {
                sync_apps($channel, $arr['app']);
            }

            if (array_key_exists('sysapp', $arr) && $arr['sysapp']) {
                sync_sysapps($channel, $arr['sysapp']);
            }

            if (array_key_exists('chatroom', $arr) && $arr['chatroom']) {
                sync_chatrooms($channel, $arr['chatroom']);
            }

            if (array_key_exists('conv', $arr) && $arr['conv']) {
                import_conv($channel, $arr['conv']);
            }

            if (array_key_exists('mail', $arr) && $arr['mail']) {
                sync_mail($channel, $arr['mail']);
            }

            if (array_key_exists('event', $arr) && $arr['event']) {
                sync_events($channel, $arr['event']);
            }

            if (array_key_exists('event_item', $arr) && $arr['event_item']) {
                sync_items($channel, $arr['event_item'], ((array_key_exists('relocate', $arr)) ? $arr['relocate'] : null));
            }

            if (array_key_exists('item', $arr) && $arr['item']) {
                sync_items($channel, $arr['item'], ((array_key_exists('relocate', $arr)) ? $arr['relocate'] : null));
            }

            if (array_key_exists('menu', $arr) && $arr['menu']) {
                sync_menus($channel, $arr['menu']);
            }

            if (array_key_exists('file', $arr) && $arr['file']) {
                sync_files($channel, $arr['file']);
            }

            if (array_key_exists('wiki', $arr) && $arr['wiki']) {
                sync_items($channel, $arr['wiki'], ((array_key_exists('relocate', $arr)) ? $arr['relocate'] : null));
            }

            if (array_key_exists('channel', $arr) && is_array($arr['channel']) && count($arr['channel'])) {
                $remote_channel = $arr['channel'];
                $remote_channel['channel_id'] = $channel['channel_id'];

                if (array_key_exists('channel_pageflags', $arr['channel']) && intval($arr['channel']['channel_pageflags'])) {
                    // Several pageflags are site-specific and cannot be sync'd.
                    // Only allow those bits which are shareable from the remote and then
                    // logically OR with the local flags

                    $arr['channel']['channel_pageflags'] = $arr['channel']['channel_pageflags'] & (PAGE_HIDDEN | PAGE_AUTOCONNECT | PAGE_APPLICATION | PAGE_PREMIUM | PAGE_ADULT);
                    $arr['channel']['channel_pageflags'] = $arr['channel']['channel_pageflags'] | $channel['channel_pageflags'];
                }

                $columns = db_columns('channel');

                $disallowed = [
                    'channel_id', 'channel_account_id', 'channel_primary', 'channel_prvkey',
                    'channel_address', 'channel_notifyflags', 'channel_removed', 'channel_deleted',
                    'channel_system', 'channel_r_stream', 'channel_r_profile', 'channel_r_abook',
                    'channel_r_storage', 'channel_r_pages', 'channel_w_stream', 'channel_w_wall',
                    'channel_w_comment', 'channel_w_mail', 'channel_w_like', 'channel_w_tagwall',
                    'channel_w_chat', 'channel_w_storage', 'channel_w_pages', 'channel_a_republish',
                    'channel_a_delegate', 'channel_moved'
                ];

                foreach ($arr['channel'] as $k => $v) {
                    if (in_array($k, $disallowed)) {
                        continue;
                    }
                    if (!in_array($k, $columns)) {
                        continue;
                    }
                    $r = dbq("UPDATE channel set " . dbesc($k) . " = '" . dbesc($v)
                        . "' where channel_id = " . intval($channel['channel_id']));
                }
            }

            if (array_key_exists('abook', $arr) && is_array($arr['abook']) && count($arr['abook'])) {
                $total_friends = 0;
                $total_feeds = 0;

                $r = q(
                    "select abook_id, abook_feed from abook where abook_channel = %d",
                    intval($channel['channel_id'])
                );
                if ($r) {
                    // don't count yourself
                    $total_friends = ((count($r) > 0) ? count($r) - 1 : 0);
                    foreach ($r as $rr) {
                        if (intval($rr['abook_feed'])) {
                            $total_feeds++;
                        }
                    }
                }


                $disallowed = ['abook_id', 'abook_account', 'abook_channel', 'abook_rating', 'abook_rating_text', 'abook_not_here'];

                $fields = db_columns('abook');

                foreach ($arr['abook'] as $abook) {
                    // this is here for debugging so we can find the issue source

                    if (!is_array($abook)) {
                        btlogger('abook is not an array');
                        continue;
                    }

                    $abconfig = null;

                    if (array_key_exists('abconfig', $abook) && is_array($abook['abconfig']) && count($abook['abconfig'])) {
                        
                        $abconfig = $abook['abconfig'];
                        
                    }

                    $clean = [];

                    if ($abook['abook_xchan'] && $abook['entry_deleted']) {
                        logger('Removing abook entry for ' . $abook['abook_xchan']);

                        $r = q(
                            "select abook_id, abook_feed from abook where abook_xchan = '%s' and abook_channel = %d and abook_self = 0 limit 1",
                            dbesc($abook['abook_xchan']),
                            intval($channel['channel_id'])
                        );
                        if ($r) {
                            contact_remove($channel['channel_id'], $r[0]['abook_id']);
                            if ($total_friends) {
                                $total_friends--;
                            }
                            if (intval($r[0]['abook_feed'])) {
                                $total_feeds--;
                            }
                        }
                        continue;
                    }

                    // Perform discovery if the referenced xchan hasn't ever been seen on this hub.
                    // This relies on the undocumented behaviour that red sites send xchan info with the abook
                    // and import_author_xchan will look them up on all federated networks

                    $found = false;
                    if ($abook['abook_xchan'] && $abook['xchan_addr'] && (!in_array($abook['xchan_network'], ['token', 'unknown']))) {
                        $h = Libzot::get_hublocs($abook['abook_xchan']);
                        if ($h) {
                            $found = true;
                        } else {
                            $xhash = import_author_xchan(encode_item_xchan($abook));
                            if ($xhash) {
                                $found = true;
                            } else {
                                logger('Import of ' . $abook['xchan_addr'] . ' failed.');
                            }
                        }
                    }

                    if ((!$found) && (!in_array($abook['xchan_network'], ['nomad', 'zot6', 'activitypub']))) {
                        // just import the record.
                        $xc = [];
                        foreach ($abook as $k => $v) {
                            if (strpos($k, 'xchan_') === 0) {
                                $xc[$k] = $v;
                            }
                        }
                        $r = q(
                            "select * from xchan where xchan_hash = '%s'",
                            dbesc($xc['xchan_hash'])
                        );
                        if (!$r) {
                            xchan_store_lowlevel($xc);
                        }
                    }

                    foreach ($abook as $k => $v) {
                        if (in_array($k, $disallowed) || (strpos($k, 'abook_') !== 0)) {
                            continue;
                        }
                        if (!in_array($k, $fields)) {
                            continue;
                        }
                        $clean[$k] = $v;
                    }

                    if (!array_key_exists('abook_xchan', $clean)) {
                        continue;
                    }

                    $reconnect = false;
                    if (array_key_exists('abook_instance', $clean) && $clean['abook_instance'] && strpos($clean['abook_instance'], z_root()) === false) {
                        // guest pass or access token - don't try to probe since it is one-way
                        // we are relying on the undocumented behaviour that the abook record also contains the xchan
                        if ($abook['xchan_network'] === 'token') {
                            $clean['abook_instance'] .= ',';
                            $clean['abook_instance'] .= z_root();
                            $clean['abook_not_here'] = 0;
                        } else {
                            $clean['abook_not_here'] = 1;
                            if (!($abook['abook_pending'] || $abook['abook_blocked'])) {
                                $reconnect = true;
                            }
                        }
                    }

                    $r = q(
                        "select * from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
                        dbesc($clean['abook_xchan']),
                        intval($channel['channel_id'])
                    );

                    // make sure we have an abook entry for this xchan on this system

                    if (!$r) {
                        if ($max_friends !== false && $total_friends > $max_friends) {
                            logger('total_channels service class limit exceeded');
                            continue;
                        }
                        if ($max_feeds !== false && intval($clean['abook_feed']) && $total_feeds > $max_feeds) {
                            logger('total_feeds service class limit exceeded');
                            continue;
                        }
                        abook_store_lowlevel(
                            [
                                'abook_xchan' => $clean['abook_xchan'],
                                'abook_account' => $channel['channel_account_id'],
                                'abook_channel' => $channel['channel_id']
                            ]
                        );
                        $total_friends++;
                        if (intval($clean['abook_feed'])) {
                            $total_feeds++;
                        }
                    }

                    if (count($clean)) {
                        foreach ($clean as $k => $v) {
                            if ($k == 'abook_dob') {
                                $v = dbescdate($v);
                            }

                            $r = dbq("UPDATE abook set " . dbesc($k) . " = '" . dbesc($v)
                                . "' where abook_xchan = '" . dbesc($clean['abook_xchan']) . "' and abook_channel = " . intval($channel['channel_id']));
                        }
                    }

                    // This will set abconfig vars if the sender is using old-style fixed permissions
                    // using the raw abook record as passed to us. New-style permissions will fall through
                    // and be set using abconfig

                    // translate_abook_perms_inbound($channel,$abook);

                    if ($abconfig) {
                        /// @fixme does not handle sync of del_abconfig
                        foreach ($abconfig as $abc) {
                            if ($abc['cat'] ===  'system' && $abc['k'] === 'my_perms' && $schema !== 'streams') {
                                $x = explode(',', $abc['v']);
                                if (in_array('view_stream',$x)  && ! in_array('deliver_stream',$x)) {
                                    $x[] = 'deliver_stream';
                                }
                                set_abconfig($channel['channel_id'], $abc['xchan'], $abc['cat'], $abc['k'], implode(',', $x));
                            }
                            else {
                                set_abconfig($channel['channel_id'], $abc['xchan'], $abc['cat'], $abc['k'], $abc['v']);
                            }
                        }
                    }
                    if ($reconnect) {
                        Connect::connect($channel, $abook['abook_xchan']);
                    }
                }
            }

            // sync collections (privacy groups) oh joy...

            if (array_key_exists('collections', $arr) && is_array($arr['collections']) && count($arr['collections'])) {
                $x = q(
                    "select * from pgrp where uid = %d ",
                    intval($channel['channel_id'])
                );
                foreach ($arr['collections'] as $cl) {
                    $found = false;
                    if ($x) {
                        foreach ($x as $y) {
                            if ($cl['collection'] == $y['hash']) {
                                $found = true;
                                break;
                            }
                        }
                        if ($found) {
                            if (
                                ($y['gname'] != $cl['name'])
                                || ($y['visible'] != $cl['visible'])
                                || ($y['deleted'] != $cl['deleted'])
                            ) {
                                q(
                                    "update pgrp set gname = '%s', visible = %d, deleted = %d where hash = '%s' and uid = %d",
                                    dbesc($cl['name']),
                                    intval($cl['visible']),
                                    intval($cl['deleted']),
                                    dbesc($cl['collection']),
                                    intval($channel['channel_id'])
                                );
                            }
                            if (intval($cl['deleted']) && (!intval($y['deleted']))) {
                                q(
                                    "delete from pgrp_member where gid = %d",
                                    intval($y['id'])
                                );
                            }
                        }
                    }
                    if (!$found) {
                        $r = q(
                            "INSERT INTO pgrp ( hash, uid, visible, deleted, gname, rule )
							VALUES( '%s', %d, %d, %d, '%s', '%s' ) ",
                            dbesc($cl['collection']),
                            intval($channel['channel_id']),
                            intval($cl['visible']),
                            intval($cl['deleted']),
                            dbesc($cl['name']),
                            dbesc($cl['rule'])
                        );
                    }

                    // now look for any collections locally which weren't in the list we just received.
                    // They need to be removed by marking deleted and removing the members.
                    // This shouldn't happen except for clones created before this function was written.

                    if ($x) {
                        $found_local = false;
                        foreach ($x as $y) {
                            foreach ($arr['collections'] as $cl) {
                                if ($cl['collection'] == $y['hash']) {
                                    $found_local = true;
                                    break;
                                }
                            }
                            if (!$found_local) {
                                q(
                                    "delete from pgrp_member where gid = %d",
                                    intval($y['id'])
                                );
                                q(
                                    "update pgrp set deleted = 1 where id = %d and uid = %d",
                                    intval($y['id']),
                                    intval($channel['channel_id'])
                                );
                            }
                        }
                    }
                }

                // reload the group list with any updates
                $x = q(
                    "select * from pgrp where uid = %d",
                    intval($channel['channel_id'])
                );

                // now sync the members

                if (
                    array_key_exists('collection_members', $arr)
                    && is_array($arr['collection_members'])
                    && count($arr['collection_members'])
                ) {
                    // first sort into groups keyed by the group hash
                    $members = [];
                    foreach ($arr['collection_members'] as $cm) {
                        if (!array_key_exists($cm['collection'], $members)) {
                            $members[$cm['collection']] = [];
                        }

                        $members[$cm['collection']][] = $cm['member'];
                    }

                    // our group list is already synchronised
                    if ($x) {
                        foreach ($x as $y) {
                            // for each group, loop on members list we just received
                            if (isset($y['hash']) && isset($members[$y['hash']])) {
                                foreach ($members[$y['hash']] as $member) {
                                    $found = false;
                                    $z = q(
                                        "select xchan from pgrp_member where gid = %d and uid = %d and xchan = '%s' limit 1",
                                        intval($y['id']),
                                        intval($channel['channel_id']),
                                        dbesc($member)
                                    );
                                    if ($z) {
                                        $found = true;
                                    }

                                    // if somebody is in the group that wasn't before - add them

                                    if (!$found) {
                                        q(
                                            "INSERT INTO pgrp_member (uid, gid, xchan)
											VALUES( %d, %d, '%s' ) ",
                                            intval($channel['channel_id']),
                                            intval($y['id']),
                                            dbesc($member)
                                        );
                                    }
                                }
                            }

                            // now retrieve a list of members we have on this site
                            $m = q(
                                "select xchan from pgrp_member where gid = %d and uid = %d",
                                intval($y['id']),
                                intval($channel['channel_id'])
                            );
                            if ($m) {
                                foreach ($m as $mm) {
                                    // if the local existing member isn't in the list we just received - remove them
                                    if (!in_array($mm['xchan'], $members[$y['hash']])) {
                                        q(
                                            "delete from pgrp_member where xchan = '%s' and gid = %d and uid = %d",
                                            dbesc($mm['xchan']),
                                            intval($y['id']),
                                            intval($channel['channel_id'])
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (array_key_exists('profile', $arr) && is_array($arr['profile']) && count($arr['profile'])) {
                $disallowed = array('id', 'aid', 'uid', 'guid');

                foreach ($arr['profile'] as $profile) {
                    $x = q(
                        "select * from profile where profile_guid = '%s' and uid = %d limit 1",
                        dbesc($profile['profile_guid']),
                        intval($channel['channel_id'])
                    );
                    if (!$x) {
                        Channel::profile_store_lowlevel(
                            [
                                'aid' => $channel['channel_account_id'],
                                'uid' => $channel['channel_id'],
                                'profile_guid' => $profile['profile_guid'],
                            ]
                        );

                        $x = q(
                            "select * from profile where profile_guid = '%s' and uid = %d limit 1",
                            dbesc($profile['profile_guid']),
                            intval($channel['channel_id'])
                        );
                        if (!$x) {
                            continue;
                        }
                    }
                    $clean = [];
                    foreach ($profile as $k => $v) {
                        if (in_array($k, $disallowed)) {
                            continue;
                        }

                        if ($profile['is_default'] && in_array($k, ['photo', 'thumb'])) {
                            continue;
                        }

                        if ($k === 'name') {
                            $clean['fullname'] = $v;
                        } elseif ($k === 'with') {
                            $clean['partner'] = $v;
                        } elseif ($k === 'work') {
                            $clean['employment'] = $v;
                        } elseif (array_key_exists($k, $x[0])) {
                            $clean[$k] = $v;
                        }

                        /**
                         * @TODO
                         * We also need to import local photos if a custom photo is selected
                         */

                        if ((strpos($profile['thumb'], '/photo/profile/l/') !== false) || intval($profile['is_default'])) {
                            $profile['photo'] = z_root() . '/photo/profile/l/' . $channel['channel_id'];
                            $profile['thumb'] = z_root() . '/photo/profile/m/' . $channel['channel_id'];
                        } else {
                            $profile['photo'] = z_root() . '/photo/' . basename($profile['photo']);
                            $profile['thumb'] = z_root() . '/photo/' . basename($profile['thumb']);
                        }
                    }

                    if (count($clean)) {
                        foreach ($clean as $k => $v) {
                            $r = dbq("UPDATE profile set " . TQUOT . dbesc($k) . TQUOT . " = '" . dbesc($v)
                                . "' where profile_guid = '" . dbesc($profile['profile_guid'])
                                . "' and uid = " . intval($channel['channel_id']));
                        }
                    }
                }
            }

            $addon = ['channel' => $channel, 'data' => $arr];
            /**
             * @hooks process_channel_sync_delivery
             *   Called when accepting delivery of a 'sync packet' containing structure and table updates from a channel clone.
             *   * \e array \b channel
             *   * \e array \b data
             */
            Hook::call('process_channel_sync_delivery', $addon);

            $DR = new DReport(z_root(), $d, $d, 'sync', 'channel sync delivered');

            $DR->set_name($channel['channel_name'] . ' <' . Channel::get_webfinger($channel) . '>');

            $result[] = $DR->get();
        }

        return $result;
    }

    /**
     * @brief Synchronises locations.
     *
     * @param array $sender
     * @param array $arr
     * @return array
     */

    public static function sync_locations($sender, $arr)
    {

        $ret = [];
        $what = EMPTY_STR;
        $changed = false;

        // If a sender reports that the channel has been deleted, delete its hubloc

        if (isset($arr['deleted_locally']) && intval($arr['deleted_locally'])) {
            q(
                "UPDATE hubloc SET hubloc_deleted = 1, hubloc_updated = '%s' WHERE hubloc_hash = '%s' AND hubloc_url = '%s'",
                dbesc(datetime_convert()),
                dbesc($sender['hash']),
                dbesc($sender['site']['url'])
            );
        }

        if ($arr['locations']) {
            $x = q(
                "select * from xchan where xchan_hash = '%s'",
                dbesc($sender['hash'])
            );
            if ($x) {
                $xchan = array_shift($x);
            }

            Libzot::check_location_move($sender['hash'], $arr['locations']);
    
            $xisting = q(
                "select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 ",
                dbesc($sender['hash'])
            );

            if (!$xisting) {
                $xisting = [];
            }

            // See if a primary is specified

            $has_primary = false;
            foreach ($arr['locations'] as $location) {
                if ($location['primary']) {
                    $has_primary = true;
                    break;
                }
            }

            // Ensure that they have one primary hub

            if (!$has_primary) {
                $arr['locations'][0]['primary'] = true;
            }

            foreach ($arr['locations'] as $location) {

                $network = isset($location['driver']) ? $location['driver'] : 'zot6';
                // only set nomad if the location info is coming from the same site as the original zotinfo packet
                if (isset($sender['site']) && isset($sender['site']['url']) && $sender['site']['url'] === $location['url']) {
                    if (isset($sender['site']['protocol_version']) && intval($sender['site']['protocol_version']) > 10) {                   
                        $network = 'nomad';
                    }
                }
  
                if (!Libzot::verify($location['url'], $location['url_sig'], $sender['public_key'])) {
                    logger('Unable to verify site signature for ' . $location['url']);
                    $ret['message'] .= sprintf(t('Unable to verify site signature for %s'), $location['url']) . EOL;
                    continue;
                }

                for ($x = 0; $x < count($xisting); $x++) {
                    if (
                        ($xisting[$x]['hubloc_url'] === $location['url'])
                        && ($xisting[$x]['hubloc_sitekey'] === $location['sitekey'])
                    ) {
                        $xisting[$x]['updated'] = true;
                    }
                }

                if (!$location['sitekey']) {
                    logger('Empty hubloc sitekey. ' . print_r($location, true));
                    continue;
                }

                // match as many fields as possible in case anything at all changed.

                $r = q(
                    "select * from hubloc where hubloc_hash = '%s' and hubloc_guid = '%s' and hubloc_guid_sig = '%s' and hubloc_id_url = '%s' and hubloc_url = '%s' and hubloc_url_sig = '%s' and hubloc_host = '%s' and hubloc_addr = '%s' and hubloc_callback = '%s' and hubloc_sitekey = '%s' and hubloc_deleted = 0 ",
                    dbesc($sender['hash']),
                    dbesc($sender['id']),
                    dbesc($sender['id_sig']),
                    dbesc($location['id_url']),
                    dbesc($location['url']),
                    dbesc($location['url_sig']),
                    dbesc($location['host']),
                    dbesc($location['address']),
                    dbesc($location['callback']),
                    dbesc($location['sitekey'])
                );
                if ($r) {
                    logger('Hub exists: ' . $location['url'], LOGGER_DEBUG);

                    // generate a new hubloc_site_id if it's wrong due to historical bugs 2021-11-30

                    if ($r[0]['hubloc_site_id'] !== $location['site_id']) {
                        q(
                            "update hubloc set hubloc_site_id = '%s' where hubloc_id = %d",
                            dbesc(Libzot::make_xchan_hash($location['url'], $location['sitekey'])),
                            intval($r[0]['hubloc_id'])
                        );
                    }

                    // update connection timestamp if this is the site we're talking to
                    // This only happens when called from import_xchan

                    $current_site = false;

                    $t = datetime_convert('UTC', 'UTC', 'now - 15 minutes');

                    if (isset($location['driver']) && $location['driver'] === 'nomad' && $location['driver'] !== $r[0]['hubloc_network']) {
                        q("update hubloc set hubloc_network = '%s' where hubloc_id = %d",
                            dbesc($location['driver']),
                            intval($r[0]['hubloc_id'])
                        );
                    }                                                                                          

                    if (array_key_exists('site', $arr) && $location['url'] == $arr['site']['url']) {
                        q(
                            "update hubloc set hubloc_connected = '%s', hubloc_updated = '%s' where hubloc_id = %d and hubloc_updated < '%s'",
                            dbesc(datetime_convert()),
                            dbesc(datetime_convert()),
                            intval($r[0]['hubloc_id']),
                            dbesc($t)
                        );
                        $current_site = true;
                    }

                    if ($current_site && (intval($r[0]['hubloc_error']) || intval($r[0]['hubloc_deleted']))) {
                        q(
                            "update hubloc set hubloc_error = 0, hubloc_deleted = 0 where hubloc_id = %d",
                            intval($r[0]['hubloc_id'])
                        );
                        if (intval($r[0]['hubloc_orphancheck'])) {
                            q(
                                "update hubloc set hubloc_orphancheck = 0 where hubloc_id = %d",
                                intval($r[0]['hubloc_id'])
                            );
                        }
                        q(
                            "update xchan set xchan_orphan = 0 where xchan_orphan = 1 and xchan_hash = '%s'",
                            dbesc($sender['hash'])
                        );
                    }

                    // Remove pure duplicates
                    if (count($r) > 1) {
                        for ($h = 1; $h < count($r); $h++) {
                            q(
                                "delete from hubloc where hubloc_id = %d",
                                intval($r[$h]['hubloc_id'])
                            );
                            $what .= 'duplicate_hubloc_removed ';
                            $changed = true;
                        }
                    }

                    if (intval($r[0]['hubloc_primary']) && (!$location['primary'])) {
                        $m = q(
                            "update hubloc set hubloc_primary = 0, hubloc_updated = '%s' where hubloc_id = %d",
                            dbesc(datetime_convert()),
                            intval($r[0]['hubloc_id'])
                        );
                        $r[0]['hubloc_primary'] = intval($location['primary']);
                        hubloc_change_primary($r[0]);
                        $what .= 'primary_hub ';
                        $changed = true;
                    } elseif ((!intval($r[0]['hubloc_primary'])) && ($location['primary'])) {
                        $m = q(
                            "update hubloc set hubloc_primary = 1, hubloc_updated = '%s' where hubloc_id = %d",
                            dbesc(datetime_convert()),
                            intval($r[0]['hubloc_id'])
                        );
                        // make sure hubloc_change_primary() has current data
                        $r[0]['hubloc_primary'] = intval($location['primary']);
                        hubloc_change_primary($r[0]);
                        $what .= 'primary_hub ';
                        $changed = true;
                    } elseif (intval($r[0]['hubloc_primary']) && $xchan && $xchan['xchan_url'] !== $r[0]['hubloc_id_url']) {
                        $pr = hubloc_change_primary($r[0]);
                        if ($pr) {
                            $what .= 'xchan_primary ';
                            $changed = true;
                        }
                    }

                    if (intval($r[0]['hubloc_deleted']) && (!intval($location['deleted']))) {
                        $n = q(
                            "update hubloc set hubloc_deleted = 0, hubloc_updated = '%s' where hubloc_id = %d",
                            dbesc(datetime_convert()),
                            intval($r[0]['hubloc_id'])
                        );
                        $what .= 'undelete_hub ';
                        $changed = true;
                    } elseif ((!intval($r[0]['hubloc_deleted'])) && (intval($location['deleted']))) {
                        logger('deleting hubloc: ' . $r[0]['hubloc_addr']);
                        hubloc_delete($r[0]);
                        $what .= 'delete_hub ';
                        $changed = true;
                    }
                    continue;
                }

                // Existing hubs are dealt with. Now let's process any new ones.
                // New hub claiming to be primary. Make it so by removing any existing primaries.

                if (intval($location['primary'])) {
                    $r = q(
                        "update hubloc set hubloc_primary = 0, hubloc_updated = '%s' where hubloc_hash = '%s' and hubloc_primary = 1",
                        dbesc(datetime_convert()),
                        dbesc($sender['hash'])
                    );
                }

                logger('New hub: ' . $location['url']);

                $r = hubloc_store_lowlevel(
                    [
                        'hubloc_guid' => $sender['id'],
                        'hubloc_guid_sig' => $sender['id_sig'],
                        'hubloc_id_url' => $location['id_url'],
                        'hubloc_hash' => $sender['hash'],
                        'hubloc_addr' => $location['address'],
                        'hubloc_network' => $network,
                        'hubloc_primary' => intval($location['primary']),
                        'hubloc_url' => $location['url'],
                        'hubloc_url_sig' => $location['url_sig'],
                        'hubloc_site_id' => Libzot::make_xchan_hash($location['url'], $location['sitekey']),
                        'hubloc_host' => $location['host'],
                        'hubloc_callback' => $location['callback'],
                        'hubloc_sitekey' => $location['sitekey'],
                        'hubloc_updated' => datetime_convert(),
                        'hubloc_connected' => datetime_convert()
                    ]
                );

                $what .= 'newhub ';
                $changed = true;

                if ($location['primary']) {
                    $r = q(
                        "select * from hubloc where hubloc_addr = '%s' and hubloc_sitekey = '%s' limit 1",
                        dbesc($location['address']),
                        dbesc($location['sitekey'])
                    );
                    if ($r) {
                        hubloc_change_primary($r[0]);
                    }
                }
            }

            // get rid of any hubs we have for this channel which weren't reported.

            if ($xisting) {
                foreach ($xisting as $x) {
                    if (!array_key_exists('updated', $x)) {
                        logger('Deleting unreferenced hub location ' . $x['hubloc_addr']);
                        hubloc_delete($x);
                        $what .= 'removed_hub ';
                        $changed = true;
                    }
                }
            }
        } else {
            logger('No locations to sync!');
        }

        $ret['change_message'] = $what;
        $ret['changed'] = $changed;

        return $ret;
    }


    public static function keychange($channel, $arr)
    {

        // verify the keychange operation
        if (!Libzot::verify($arr['channel']['channel_pubkey'], $arr['keychange']['new_sig'], $channel['channel_prvkey'])) {
            logger('sync keychange: verification failed');
            return;
        }

        $sig = Libzot::sign($channel['channel_guid'], $arr['channel']['channel_prvkey']);
        $hash = Libzot::make_xchan_hash($channel['channel_guid'], $arr['channel']['channel_pubkey']);


        $r = q(
            "update channel set channel_prvkey = '%s', channel_pubkey = '%s', channel_guid_sig = '%s',
			channel_hash = '%s' where channel_id = %d",
            dbesc($arr['channel']['channel_prvkey']),
            dbesc($arr['channel']['channel_pubkey']),
            dbesc($sig),
            dbesc($hash),
            intval($channel['channel_id'])
        );
        if (!$r) {
            logger('keychange sync: channel update failed');
            return;
        }

        $r = q(
            "select * from channel where channel_id = %d",
            intval($channel['channel_id'])
        );

        if (!$r) {
            logger('keychange sync: channel retrieve failed');
            return;
        }

        $channel = $r[0];

        $h = q(
            "select * from hubloc where hubloc_hash = '%s' and hubloc_url = '%s' and hubloc_deleted = 0",
            dbesc($arr['keychange']['old_hash']),
            dbesc(z_root())
        );

        if ($h) {
            foreach ($h as $hv) {
                $hv['hubloc_guid_sig'] = $sig;
                $hv['hubloc_hash'] = $hash;
                $hv['hubloc_url_sig'] = Libzot::sign(z_root(), $channel['channel_prvkey']);
                hubloc_store_lowlevel($hv);
            }
        }

        $x = q(
            "select * from xchan where xchan_hash = '%s' ",
            dbesc($arr['keychange']['old_hash'])
        );

        $check = q(
            "select * from xchan where xchan_hash = '%s'",
            dbesc($hash)
        );

        if (($x) && (!$check)) {
            $oldxchan = $x[0];
            foreach ($x as $xv) {
                $xv['xchan_guid_sig'] = $sig;
                $xv['xchan_hash'] = $hash;
                $xv['xchan_pubkey'] = $channel['channel_pubkey'];
                $xv['xchan_updated'] = datetime_convert();
                xchan_store_lowlevel($xv);
                $newxchan = $xv;
            }
        }

        $a = q(
            "select * from abook where abook_xchan = '%s' and abook_self = 1",
            dbesc($arr['keychange']['old_hash'])
        );

        if ($a) {
            q(
                "update abook set abook_xchan = '%s' where abook_id = %d",
                dbesc($hash),
                intval($a[0]['abook_id'])
            );
        }

        xchan_change_key($oldxchan, $newxchan, $arr['keychange']);
    }
}
