<?php

namespace Zotlabs\Lib;

use App;
use Zotlabs\Lib\ServiceClass;    
use Zotlabs\Access\Permissions;
use Zotlabs\Daemon\Run;
use Zotlabs\Extend\Hook;

class Connect
{

    /**
     * Takes a $channel and a $url/handle and adds a new connection
     *
     * Returns array
     *  $return['success'] boolean true if successful
     *  $return['abook'] Address book entry joined with xchan if successful
     *  $return['message'] error text if success is false.
     *
     * This function does NOT send sync packets to clones. The caller is responsible for doing this
     */

    public static function connect($channel, $url, $sub_channel = false)
    {

        $uid = $channel['channel_id'];

        if (strpos($url, '@') === false && strpos($url, '/') === false) {
            $url = $url . '@' . App::get_hostname();
        }

        $result = ['success' => false, 'message' => ''];

        $my_perms = false;
        $protocol = '';

        $ap_allowed = get_config('system', 'activitypub', ACTIVITYPUB_ENABLED) && get_pconfig($uid, 'system', 'activitypub', ACTIVITYPUB_ENABLED);

        if (substr($url, 0, 1) === '[') {
            $x = strpos($url, ']');
            if ($x) {
                $protocol = substr($url, 1, $x - 1);
                $url = substr($url, $x + 1);
            }
        }

        if (!check_siteallowed($url)) {
            $result['message'] = t('Channel is blocked on this site.');
            return $result;
        }

        if (!$url) {
            $result['message'] = t('Channel location missing.');
            return $result;
        }

        // check service class limits

        $r = q(
            "select count(*) as total from abook where abook_channel = %d and abook_self = 0 ",
            intval($uid)
        );
        if ($r) {
            $total_channels = $r[0]['total'];
        }

        if (!ServiceClass::allows($uid, 'total_channels', $total_channels)) {
            $result['message'] = ServiceClass::upgrade_message();
            return $result;
        }

        $xchan_hash = '';
        $sql_options = (($protocol) ? " and xchan_network = '" . dbesc($protocol) . "' " : '');

        $r = q(
            "select * from xchan where ( xchan_hash = '%s' or xchan_url = '%s' or xchan_addr = '%s') $sql_options ",
            dbesc($url),
            dbesc($url),
            dbesc($url)
        );

        if ($r) {
            // reset results to the best record or the first if we don't have the best
            // note: this returns a single record and not an array of records

            $r = Libzot::zot_record_preferred($r, 'xchan_network');

            // ensure there's a valid hubloc for this xchan before proceeding - you cannot connect without it

            if (in_array($r['xchan_network'], ['nomad', 'zot6', 'activitypub'])) {
                $h = q(
                    "select * from hubloc where hubloc_hash = '%s'",
                    dbesc($r['xchan_hash'])
                );
                if (!$h) {
                    $r = null;
                }
            }

            // we may have nulled out this record so check again

            if ($r) {
                // Check the site table to see if we should have a zot6 hubloc,
                // If so, clear the xchan and start fresh

                if ($r['xchan_network'] === 'activitypub') {
                    $m = parse_url($r['xchan_hash']);
                    unset($m['path']);
                    $h = unparse_url($m);
                    $s = q(
                        "select * from site where site_url = '%s'",
                        dbesc($h)
                    );
                    if (intval($s['site_type']) === SITE_TYPE_ZOT) {
                        logger('got zot - ignore activitypub entry');
                        $r = null;
                    }
                }
            }
        }


        $singleton = false;

        if (!$r) {
            // not in cache - try discovery

            $wf = discover_by_webbie($url, $protocol, false);

            if (!$wf) {
                $result['message'] = t('Remote channel or protocol unavailable.');
                return $result;
            }
        }

        if ($wf) {
            // something was discovered - find the record which was just created.

            $r = q(
                "select * from xchan where ( xchan_hash = '%s' or xchan_url = '%s' or xchan_addr = '%s' ) $sql_options",
                dbesc(($wf) ? $wf : $url),
                dbesc($url),
                dbesc($url)
            );

            // convert to a single record (once again preferring a zot solution in the case of multiples)

            if ($r) {
                $r = Libzot::zot_record_preferred($r, 'xchan_network');
            }
        }

        // if discovery was a success or the channel was already cached we should have an xchan record in $r

        if ($r) {
            $xchan = $r;
            $xchan_hash = $r['xchan_hash'];
            $their_perms = EMPTY_STR;
        }

        // failure case

        if (!$xchan_hash) {
            $result['message'] = t('Channel discovery failed.');
            logger('follow: ' . $result['message']);
            return $result;
        }

        if (!check_channelallowed($xchan_hash)) {
            $result['message'] = t('Channel is blocked on this site.');
            logger('follow: ' . $result['message']);
            return $result;
        }


        if ($r['xchan_network'] === 'activitypub') {
            if (!$ap_allowed) {
                $result['message'] = t('Protocol not supported');
                return $result;
            }
            $singleton = true;
        }

        // Now start processing the new connection

        $aid = $channel['channel_account_id'];
        $hash = $channel['channel_hash'];
        $default_group = $channel['channel_default_group'];

        if ($hash === $xchan_hash) {
            $result['message'] = t('Cannot connect to yourself.');
            return $result;
        }

        $p = Permissions::connect_perms($uid);

        // parent channels have unencumbered write permission

        if ($sub_channel) {
            $p['perms']['post_wall'] = 1;
            $p['perms']['post_comments'] = 1;
            $p['perms']['write_storage'] = 1;
            $p['perms']['post_like'] = 1;
            $p['perms']['delegate'] = 0;
            $p['perms']['moderated'] = 0;
        }

        $my_perms = Permissions::serialise($p['perms']);

        $profile_assign = get_pconfig($uid, 'system', 'profile_assign', '');

        // See if we are already connected by virtue of having an abook record

        $r = q(
            "select abook_id, abook_xchan, abook_pending, abook_instance from abook 
			where abook_xchan = '%s' and abook_channel = %d limit 1",
            dbesc($xchan_hash),
            intval($uid)
        );

        if ($r) {
            $abook_instance = $r[0]['abook_instance'];

            // If they are on a non-nomadic network, add them to this location

            if (($singleton) && strpos($abook_instance, z_root()) === false) {
                if ($abook_instance) {
                    $abook_instance .= ',';
                }
                $abook_instance .= z_root();

                $x = q(
                    "update abook set abook_instance = '%s', abook_not_here = 0 where abook_id = %d",
                    dbesc($abook_instance),
                    intval($r[0]['abook_id'])
                );
            }

            // if they have a pending connection, we just followed them so approve the connection request

            if (intval($r[0]['abook_pending'])) {
                $x = q(
                    "update abook set abook_pending = 0 where abook_id = %d",
                    intval($r[0]['abook_id'])
                );
            }
        } else {
            // create a new abook record

            $closeness = get_pconfig($uid, 'system', 'new_abook_closeness', 80);

            $r = abook_store_lowlevel(
                [
                    'abook_account' => intval($aid),
                    'abook_channel' => intval($uid),
                    'abook_closeness' => intval($closeness),
                    'abook_xchan' => $xchan_hash,
                    'abook_profile' => $profile_assign,
                    'abook_feed' => intval(($xchan['xchan_network'] === 'rss') ? 1 : 0),
                    'abook_created' => datetime_convert(),
                    'abook_updated' => datetime_convert(),
                    'abook_instance' => (($singleton) ? z_root() : '')
                ]
            );
        }

        if (!$r) {
            logger('abook creation failed');
            $result['message'] = t('error saving data');
            return $result;
        }

        // Set suitable permissions to the connection

        if ($my_perms) {
            set_abconfig($uid, $xchan_hash, 'system', 'my_perms', $my_perms);
        }

        // fetch the entire record

        $r = q(
            "select abook.*, xchan.* from abook left join xchan on abook_xchan = xchan_hash 
			where abook_xchan = '%s' and abook_channel = %d limit 1",
            dbesc($xchan_hash),
            intval($uid)
        );

        if ($r) {
            $result['abook'] = array_shift($r);
            Run::Summon(['Notifier', 'permissions_create', $result['abook']['abook_id']]);
        }

        $arr = ['channel_id' => $uid, 'channel' => $channel, 'abook' => $result['abook']];

        Hook::call('follow', $arr);

        /** If there is a default group for this channel, add this connection to it */

        if ($default_group) {
            $g = AccessList::rec_byhash($uid, $default_group);
            if ($g) {
                AccessList::member_add($uid, '', $xchan_hash, $g['id']);
            }
        }

        $result['success'] = true;
        return $result;
    }
}
