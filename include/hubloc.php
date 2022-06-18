<?php

/**
 * @file include/hubloc.php
 * @brief Hubloc related functions.
 */

use Code\Daemon\Run;

/**
 * @brief Create an array for hubloc table and insert record.
 *
 * Creates an assoziative array which will be inserted into the hubloc table.
 *
  * @param array $arr An assoziative array with hubloc values
 * @return bool|PDOStatement
 */
function hubloc_store_lowlevel($arr)
{
    $update = array_key_exists('hubloc_id',$arr) && $arr['hubloc_id'] ? 'hubloc_id = ' . intval($arr['hubloc_id']) : false;
     
    $store = [
        'hubloc_guid'        => ((array_key_exists('hubloc_guid', $arr))        ? $arr['hubloc_guid']        : ''),
        'hubloc_guid_sig'    => ((array_key_exists('hubloc_guid_sig', $arr))    ? $arr['hubloc_guid_sig']    : ''),
        'hubloc_id_url'      => ((array_key_exists('hubloc_id_url', $arr))      ? $arr['hubloc_id_url']    : ''),
        'hubloc_hash'        => ((array_key_exists('hubloc_hash', $arr))        ? $arr['hubloc_hash']        : ''),
        'hubloc_addr'        => ((array_key_exists('hubloc_addr', $arr))        ? $arr['hubloc_addr']        : ''),
        'hubloc_network'     => ((array_key_exists('hubloc_network', $arr))     ? $arr['hubloc_network']     : ''),
        'hubloc_flags'       => ((array_key_exists('hubloc_flags', $arr))       ? $arr['hubloc_flags']       : 0),
        'hubloc_status'      => ((array_key_exists('hubloc_status', $arr))      ? $arr['hubloc_status']      : 0),
        'hubloc_url'         => ((array_key_exists('hubloc_url', $arr))         ? $arr['hubloc_url']         : ''),
        'hubloc_url_sig'     => ((array_key_exists('hubloc_url_sig', $arr))     ? $arr['hubloc_url_sig']     : ''),
        'hubloc_site_id'     => ((array_key_exists('hubloc_site_id', $arr))     ? $arr['hubloc_site_id']     : ''),
        'hubloc_host'        => ((array_key_exists('hubloc_host', $arr))        ? $arr['hubloc_host']        : ''),
        'hubloc_callback'    => ((array_key_exists('hubloc_callback', $arr))    ? $arr['hubloc_callback']    : ''),
        'hubloc_connect'     => ((array_key_exists('hubloc_connect', $arr))     ? $arr['hubloc_connect']     : ''),
        'hubloc_sitekey'     => ((array_key_exists('hubloc_sitekey', $arr))     ? $arr['hubloc_sitekey']     : ''),
        'hubloc_updated'     => ((array_key_exists('hubloc_updated', $arr))     ? $arr['hubloc_updated']     : NULL_DATE),
        'hubloc_connected'   => ((array_key_exists('hubloc_connected', $arr))   ? $arr['hubloc_connected']   : NULL_DATE),
        'hubloc_primary'     => ((array_key_exists('hubloc_primary', $arr))     ? $arr['hubloc_primary']     : 0),
        'hubloc_orphancheck' => ((array_key_exists('hubloc_orphancheck', $arr)) ? $arr['hubloc_orphancheck'] : 0),
        'hubloc_error'       => ((array_key_exists('hubloc_error', $arr))       ? $arr['hubloc_error']       : 0),
        'hubloc_deleted'     => ((array_key_exists('hubloc_deleted', $arr))     ? $arr['hubloc_deleted']     : 0)
    ];
    
    return $update ? update_table_from_array('hubloc', $store, $update) : create_table_from_array('hubloc', $store);
}

function site_store_lowlevel($arr)
{

    $store = [
        'site_url'        => ((array_key_exists('site_url', $arr))        ? $arr['site_url']         : ''),
        'site_access'     => ((array_key_exists('site_access', $arr))     ? $arr['site_access']      : 0),
        'site_flags'      => ((array_key_exists('site_flags', $arr))      ? $arr['site_flags']       : 0),
        'site_update'     => ((array_key_exists('site_update', $arr))     ? $arr['site_update']      : NULL_DATE),
        'site_pull'       => ((array_key_exists('site_pull', $arr))       ? $arr['site_pull']        : NULL_DATE),
        'site_sync'       => ((array_key_exists('site_sync', $arr))       ? $arr['site_sync']        : NULL_DATE),
        'site_directory'  => ((array_key_exists('site_directory', $arr))  ? $arr['site_directory']   : ''),
        'site_register'   => ((array_key_exists('site_register', $arr))   ? $arr['site_register']    : 0),
        'site_sellpage'   => ((array_key_exists('site_sellpage', $arr))   ? $arr['site_sellpage']    : ''),
        'site_location'   => ((array_key_exists('site_location', $arr))   ? $arr['site_location']    : ''),
        'site_realm'      => ((array_key_exists('site_realm', $arr))      ? $arr['site_realm']       : ''),
        'site_valid'      => ((array_key_exists('site_valid', $arr))      ? $arr['site_valid']       : 0),
        'site_dead'       => ((array_key_exists('site_dead', $arr))       ? $arr['site_dead']        : 0),
        'site_type'       => ((array_key_exists('site_type', $arr))       ? $arr['site_type']        : 0),
        'site_project'    => ((array_key_exists('site_project', $arr))    ? $arr['site_project']     : ''),
        'site_version'    => ((array_key_exists('site_version', $arr))    ? $arr['site_version']     : ''),
        'site_crypto'     => ((array_key_exists('site_crypto', $arr))     ? $arr['site_crypto']      : '')
    ];

    return create_table_from_array('site', $store);
}





function prune_hub_reinstalls()
{

    $r = q(
        "select site_url from site where site_type = %d",
        intval(SITE_TYPE_ZOT)
    );
    if ($r) {
        foreach ($r as $rr) {
            $x = q(
                "select count(*) as t, hubloc_sitekey, max(hubloc_connected) as c from hubloc where hubloc_url = '%s' group by hubloc_sitekey order by c",
                dbesc($rr['site_url'])
            );

            // see if this url has more than one sitekey, indicating it has been re-installed.

            if (count($x) > 1) {
                $d1 = datetime_convert('UTC', 'UTC', $x[0]['c']);
                $d2 = datetime_convert('UTC', 'UTC', 'now - 3 days');

                // allow some slop period, say 3 days - just in case this is a glitch or transient occurrence
                // Then remove any hublocs pointing to the oldest entry.

                if (($d1 < $d2) && ($x[0]['hubloc_sitekey'])) {
                    logger('prune_hub_reinstalls: removing dead hublocs at ' . $rr['site_url']);
                    $y = q(
                        "delete from hubloc where hubloc_sitekey = '%s'",
                        dbesc($x[0]['hubloc_sitekey'])
                    );
                }
            }
        }
    }
}


/**
 * @brief Remove obsolete hublocs.
 *
 * Get rid of any hublocs which are ours but aren't valid anymore -
 * e.g. they point to a different and perhaps transient URL that we aren't using.
 *
 * I need to stress that this shouldn't happen. fix_system_urls() fixes hublocs
 * when it discovers the URL has changed. So it's unclear how we could end up
 * with URLs pointing to the old site name. But it happens. This may be an artifact
 * of an old bug or maybe a regression in some newer code. In any event, they
 * mess up communications and we have to take action if we find any.
 */
function remove_obsolete_hublocs()
{

    logger('remove_obsolete_hublocs', LOGGER_DEBUG);

    // First make sure we have any hublocs (at all) with this URL and sitekey.
    // We don't want to perform this operation while somebody is in the process
    // of renaming their hub or installing certs.

    $r = q(
        "select hubloc_id from hubloc where hubloc_url = '%s' and hubloc_sitekey = '%s'",
        dbesc(z_root()),
        dbesc(get_config('system', 'pubkey'))
    );
    if ((! $r) || (! count($r))) {
        return;
    }

    // Good. We have at least one *valid* hubloc.

    // Do we have any invalid ones?

    $r = q(
        "select hubloc_id from hubloc where hubloc_sitekey = '%s' and hubloc_url != '%s'",
        dbesc(get_config('system', 'pubkey')),
        dbesc(z_root())
    );
    $p = q(
        "select hubloc_id from hubloc where hubloc_sitekey != '%s' and hubloc_url = '%s'",
        dbesc(get_config('system', 'pubkey')),
        dbesc(z_root())
    );
    if (is_array($r) && is_array($p)) {
        $r = array_merge($r, $p);
    }

    if (! $r) {
        return;
    }

    // We've got invalid hublocs. Get rid of them.

    logger('remove_obsolete_hublocs: removing ' . count($r) . ' hublocs.');

    $interval = ((get_config('system', 'delivery_interval') !== false)
            ? intval(get_config('system', 'delivery_interval')) : 2 );

    foreach ($r as $rr) {
        hubloc_delete($rr['hubloc_id']);

        $x = q(
            "select channel_id from channel where channel_hash = '%s' limit 1",
            dbesc($rr['hubloc_hash'])
        );
        if ($x) {
            Run::Summon([ 'Notifier', 'refresh_all', $x[0]['channel_id'] ]);
            if ($interval) {
                @time_sleep_until(microtime(true) + (float) $interval);
            }
        }
    }
}


/**
 * @brief Change primary hubloc.
 *
 * This actually changes other structures to match the given (presumably current)
 * hubloc primary selection.
 *
 * @param array $hubloc
 * @return bool
 */
function hubloc_change_primary($hubloc)
{

    if (! is_array($hubloc)) {
        logger('no hubloc');
        return false;
    }

    logger('setting primary: ' . $hubloc['hubloc_url'] . ((intval($hubloc['hubloc_primary'])) ? '  true' : ' false'));

    // See if this is a local hubloc and if so update the primary for the corresponding channel record.

    if ($hubloc['hubloc_url'] === z_root()) {
        $r = q(
            "select channel_id from channel where channel_hash = '%s' limit 1",
            dbesc($hubloc['hubloc_hash'])
        );
        if ($r) {
            q(
                "update channel set channel_primary = %d where channel_id = %d",
                intval($hubloc['hubloc_primary']),
                intval($r[0]['channel_id'])
            );
        }
    }

    // we only need to proceed further if this particular hubloc is now primary

    if (! (intval($hubloc['hubloc_primary']))) {
        logger('not primary: ' . $hubloc['hubloc_url']);
        return false;
    }

    // do we even have an xchan for this hubloc and if so is it already set as primary?

    $r = q(
        "select * from xchan where xchan_hash = '%s' limit 1",
        dbesc($hubloc['hubloc_hash'])
    );
    if (! $r) {
        logger('xchan not found');
        return false;
    }
    if ($r[0]['xchan_addr'] === $hubloc['hubloc_addr']) {
        logger('xchan already changed');
        return false;
    }

    $url = $hubloc['hubloc_url'];
    $lwebbie = substr($hubloc['hubloc_addr'], 0, strpos($hubloc['hubloc_addr'], '@'));

    $r = q(
        "update xchan set xchan_addr = '%s', xchan_url = '%s', xchan_follow = '%s', xchan_connurl = '%s' where xchan_hash = '%s'",
        dbesc($hubloc['hubloc_addr']),
        dbesc($url . '/channel/' . $lwebbie),
        dbesc($url . '/follow?f=&url=%s'),
        dbesc($url . '/poco/' . $lwebbie),
        dbesc($hubloc['hubloc_hash'])
    );
    if (! $r) {
        logger('xchan_update failed.');
    }

    logger('primary hubloc changed.' . print_r($hubloc, true), LOGGER_DEBUG);
    return true;
}


/**
 * @brief Mark a hubloc as down.
 *
 * We use the post url to distinguish between http and https hublocs.
 * The https might be alive, and the http dead.
 * Also set site_dead for the corresponding entry in the site table
 *
 * @param string $posturl Hubloc callback url which to disable
 */
function hubloc_mark_as_down($posturl)
{
    $r = q(
        "update hubloc set hubloc_status = ( hubloc_status | %d ) where hubloc_callback = '%s'",
        intval(HUBLOC_OFFLINE),
        dbesc($posturl)
    );
    // extract the baseurl and set site.site_dead to match
    $m = parse_url($posturl);
    $h = $m['scheme'] . '://' . $m['host'];
    $r = q(
        "update site set site_dead = 1 where site_url = '%s'",
        dbesc($h)
    );
}

function hubloc_delete($hubloc) {
    if (is_array($hubloc) && array_key_exists('hubloc_id',$hubloc)) {
        q("update hubloc set hubloc_deleted = 1 where hubloc_id = %d",
            intval($hubloc['hubloc_id'])
        );
    }
}
    
/**
 * @brief return comma separated string of non-dead clone locations (net addresses) for a given netid
 *
 * @param string $netid network identity (typically xchan_hash or hubloc_hash)
 * @return string
 */

function locations_by_netid($netid)
{

    $locs = q(
        "select hubloc_addr as location from hubloc left join site on hubloc_url = site_url where hubloc_hash = '%s' and hubloc_deleted = 0 and site_dead = 0",
        dbesc($netid)
    );


    return array_elm_to_str($locs, 'location', ', ', 'trim_and_unpunify');
}



function ping_site($url)
{
    $ret = [ 'success' => false ];

    $r = Code\Lib\Zotfinger::exec($url);

    if (! $r['data']) {
        $ret['message'] = 'no answer from ' . $url;
        return $ret;
    }

    $ret['success'] = true;
    return $ret;
}


function get_hubloc_addrs_by_hash($hash)
{

    return q(
        "select hubloc_addr from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
        dbesc($hash)
    );
}


function hubloc_id_query($s, $limit = 0) {

    if ($limit) {
        $qlimit = 'limit ' . intval($limit);
    }
    $r = q("select * from hubloc where (hubloc_id_url = '%s' or hubloc_hash = '%s') and hubloc_deleted = 0 $qlimit",
        dbesc($s),
        dbesc($s)
    );
    return $r;
}



function hublocx_id_query($s, $limit = 0) {

    if ($limit) {
        $qlimit = 'limit ' . intval($limit);
    }
    $r = q("select * from hubloc left join xchan on hubloc_hash = xchan_hash where (hubloc_id_url = '%s' or hubloc_hash = '%s') and hubloc_deleted = 0 $qlimit",
        dbesc($s),
        dbesc($s)
    );
    return $r;
}

function hubloc_id_addr_query($s, $limit = 0) {

    if ($limit) {
        $qlimit = 'limit ' . intval($limit);
    }
    $r = q("select * from hubloc where (hubloc_id_url = '%s' or hubloc_hash = '%s' or hubloc_addr = '%s') and hubloc_deleted = 0 $qlimit",
        dbesc($s),
        dbesc($s),
        dbesc(str_replace('acct:' , '', $s))
    );
    return $r;
}

function hublocx_id_addr_query($s, $limit = 0) {

    if ($limit) {
        $qlimit = 'limit ' . intval($limit);
    }
    $r = q("select * from hubloc left join xchan on hubloc_hash = xchan_hash where (hubloc_id_url = '%s' or hubloc_hash = '%s' or hubloc_addr = '%s') and hubloc_deleted = 0 $qlimit",
        dbesc($s),
        dbesc($s),
        dbesc(str_replace('acct:' , '', $s))
    );
    return $r;
}
