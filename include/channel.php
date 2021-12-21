<?php

/**
 * @file include/channel.php
 * @brief Channel related functions.
 */

use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\AccessList;
use Zotlabs\Lib\Crypto;
use Zotlabs\Lib\Connect;
use Zotlabs\Access\PermissionRoles;
use Zotlabs\Access\PermissionLimits;
use Zotlabs\Access\Permissions;
use Zotlabs\Daemon\Run;
use Zotlabs\Lib\System;
use Zotlabs\Render\Comanche;

require_once('include/menu.php');
require_once('include/photo_factory.php');

require_once('include/security.php');

/**
 * @brief Called when creating a new channel.
 *
 * Checks the account's service class and number of current channels to determine
 * whether creating a new channel is within the current service class constraints.
 *
 * @param int $account_id
 *     Account_id used for this request
 *
 * @returns associative array with:
 *  * \e boolean \b success boolean true if creating a new channel is allowed for this account
 *  * \e string \b message (optional) if success is false, optional error text
 *  * \e int \b total_identities
 */
function identity_check_service_class($account_id)
{
    $ret = array('success' => false, 'message' => '');

    $r = q(
        "select count(channel_id) as total from channel where channel_account_id = %d and channel_removed = 0 ",
        intval($account_id)
    );
    if (! ($r && count($r))) {
        $ret['total_identities'] = 0;
        $ret['message'] = t('Unable to obtain identity information from database');
        return $ret;
    }

    $ret['total_identities'] = intval($r[0]['total']);

    if (! account_service_class_allows($account_id, 'total_identities', $r[0]['total'])) {
        $ret['message'] .= upgrade_message();
        return $ret;
    }

    $ret['success'] = true;

    return $ret;
}


/**
 * @brief Determine if the channel name is allowed when creating a new channel.
 *
 * This action is pluggable.
 * We're currently only checking for an empty name or one that exceeds our
 * storage limit (191 chars). 191 chars is probably going to create a mess on
 * some pages.
 * Plugins can set additional policies such as full name requirements, character
 * sets, multi-byte length, etc.
 *
 * @param string $name
 * @return string describing the error state, or nil return if name is valid
 */
function validate_channelname($name)
{

    if (! $name) {
        return t('Empty name');
    }

    if (mb_strlen($name) > 191) {
        return t('Name too long');
    }

    $arr = ['name' => $name];
    /**
     * @hooks validate_channelname
     *   Used to validate the names used by a channel.
     *   * \e string \b name
     *   * \e string \b message - return error message
     */
    call_hooks('validate_channelname', $arr);

    if (x($arr, 'message')) {
        return $arr['message'];
    }
}


/**
 * @brief Create a system channel - which has no account attached.
 *
 */
function create_sys_channel()
{

    // Ensure that there is a host keypair.

    if ((! get_config('system', 'pubkey')) || (! get_config('system', 'prvkey'))) {
        $hostkey = Crypto::new_keypair(4096);
        set_config('system', 'pubkey', $hostkey['pubkey']);
        set_config('system', 'prvkey', $hostkey['prvkey']);
    }

    $sys = get_sys_channel();

    if ($sys) {
        if (isset($sys['channel_pubkey']) && $sys['channel_pubkey'] && $sys['channel_pubkey'] === get_config('system', 'pubkey')) {
            return;
        } else {
            // upgrade the sys channel and return
            $pubkey = get_config('system', 'pubkey');
            $prvkey = get_config('system', 'prvkey');
            $guid_sig = Libzot::sign($sys['channel_guid'], $prvkey);
            $hash = Libzot::make_xchan_hash($sys['channel_guid'], $pubkey);

            q(
                "update channel set channel_guid_sig = '%s', channel_hash = '%s', channel_pubkey = '%s', channel_prvkey = '%s' where channel_id = %d",
                dbesc($guid_sig),
                dbesc($hash),
                dbesc($pubkey),
                dbesc($prvkey),
                dbesc($sys['channel_id'])
            );

            q(
                "update xchan set xchan_guid_sig = '%s', xchan_hash = '%s', xchan_pubkey = '%s', xchan_url = '%s' where xchan_hash = '%s'",
                dbesc($guid_sig),
                dbesc($hash),
                dbesc($pubkey),
                dbesc(z_root()),
                dbesc($sys['channel_hash'])
            );
            q(
                "update hubloc set hubloc_guid_sig = '%s', hubloc_hash = '%s', hubloc_id_url = '%s', hubloc_url_sig = '%s', hubloc_url = '%s', hubloc_callback = '%s', hubloc_site_id = '%s', hubloc_orphancheck = 0, hubloc_error = 0, hubloc_deleted = 0 where hubloc_hash = '%s'",
                dbesc($guid_sig),
                dbesc($hash),
                dbesc(z_root()),
                dbesc(Libzot::sign(z_root(), $prvkey)),
                dbesc(z_root()),
                dbesc(z_root() . '/zot'),
                dbesc(Libzot::make_xchan_hash(z_root(), $pubkey)),
                dbesc($sys['channel_hash'])
            );

            q(
                "update abook set abook_xchan = '%s' where abook_xchan = '%s'",
                dbesc($hash),
                dbesc($sys['channel_hash'])
            );

            q(
                "update abconfig set xchan = '%s' where xchan = '%s'",
                dbesc($hash),
                dbesc($sys['channel_hash'])
            );

            return;
        }
    }

    create_identity([
            'account_id'       => 'xxx',  // Typecast trickery: account_id is required. This will create an identity with an (integer) account_id of 0
            'nickname'         => 'sys',
            'name'             => 'System',
            'permissions_role' => 'social',
            'pageflags'        => 0,
            'publish'          => 0,
            'system'           => 1
    ]);
}


/**
 * @brief Returns the sys channel.
 *
 * @return array|bool
 */
function get_sys_channel()
{

    // App::$sys_channel caches this lookup

    if (is_array(App::$sys_channel)) {
        return App::$sys_channel;
    }

    $r = q("select * from channel left join xchan on channel_hash = xchan_hash where channel_system = 1 limit 1");

    if ($r) {
        App::$sys_channel = array_shift($r);
        return App::$sys_channel;
    }
    return false;
}


/**
 * @brief Checks if $channel_id is sys channel.
 *
 * @param int $channel_id
 * @return bool
 */
function is_sys_channel($channel_id)
{
    $s = get_sys_channel();
    if ($s) {
        return (intval($s['channel_id']) === intval($channel_id));
    }
    return false;
}


/**
 * @brief Return the total number of channels on this site.
 *
 * No filtering is performed except to check PAGE_REMOVED.
 *
 * @returns int|booleean
 *   on error returns boolean false
 */
function channel_total()
{
    $r = q("select channel_id from channel where channel_removed = 0");

    if (is_array($r)) {
        return count($r);
    }

    return false;
}


/**
 * @brief Create a new channel.
 *
 * Also creates the related xchan, hubloc, profile, and "self" abook records,
 * and an empty "Friends" group/collection for the new channel.
 *
 * @param array $arr associative array with:
 *  * \e string \b name full name of channel
 *  * \e string \b nickname "email/url-compliant" nickname
 *  * \e int \b account_id to attach with this channel
 *  * [other identity fields as desired]
 *
 * @returns array
 *     'success' => boolean true or false
 *     'message' => optional error text if success is false
 *     'channel' => if successful the created channel array
 */
function create_identity($arr)
{

    $ret = array('success' => false);

    if (! $arr['account_id']) {
        $ret['message'] = t('No account identifier');
        return $ret;
    }
    $ret = identity_check_service_class($arr['account_id']);
    if (!$ret['success']) {
        return $ret;
    }
    // save this for auto_friending
    $total_identities = $ret['total_identities'];

    $nick = mb_strtolower(trim($arr['nickname']));
    if (! $nick) {
        $ret['message'] = t('Nickname is required.');
        return $ret;
    }

    $name = escape_tags($arr['name']);
    $pageflags = ((x($arr, 'pageflags')) ? intval($arr['pageflags']) : PAGE_NORMAL);
    $system = ((x($arr, 'system')) ? intval($arr['system']) : 0);
    $name_error = validate_channelname($arr['name']);
    if ($name_error) {
        $ret['message'] = $name_error;
        return $ret;
    }

    if ($nick === 'sys' && (! $system)) {
        $ret['message'] = t('Reserved nickname. Please choose another.');
        return $ret;
    }

    if (check_webbie(array($nick)) !== $nick) {
        $ret['message'] = t('Nickname has unsupported characters or is already being used on this site.');
        return $ret;
    }

    $guid = Libzot::new_uid($nick);

    if ($system) {
        $key = [ 'pubkey' => get_config('system', 'pubkey'), 'prvkey' => get_config('system', 'prvkey') ];
    } else {
        $key = Crypto::new_keypair(4096);
    }

    $sig = Libzot::sign($guid, $key['prvkey']);
    $hash = Libzot::make_xchan_hash($guid, $key['pubkey']);

    // Force a few things on the short term until we can provide a theme or app with choice

    $publish = 1;

    if (array_key_exists('publish', $arr)) {
        $publish = intval($arr['publish']);
    }

    $role_permissions = null;
    $parent_channel_hash = EMPTY_STR;

    if (array_key_exists('permissions_role', $arr) && $arr['permissions_role']) {
        $role_permissions = PermissionRoles::role_perms($arr['permissions_role']);
        if (isset($role_permissions['channel_type']) && $role_permissions['channel_type'] === 'collection') {
            $parent_channel_hash = $arr['parent_hash'];
        }
    }

    if ($role_permissions && array_key_exists('directory_publish', $role_permissions)) {
        $publish = intval($role_permissions['directory_publish']);
    }

    $primary = true;

    if (array_key_exists('primary', $arr)) {
        $primary = intval($arr['primary']);
    }

    $expire = 0;

    $r = channel_store_lowlevel(
        [
            'channel_account_id'  => intval($arr['account_id']),
            'channel_primary'     => intval($primary),
            'channel_name'        => $name,
            'channel_parent'      => $parent_channel_hash,
            'channel_address'     => $nick,
            'channel_guid'        => $guid,
            'channel_guid_sig'    => $sig,
            'channel_hash'        => $hash,
            'channel_prvkey'      => $key['prvkey'],
            'channel_pubkey'      => $key['pubkey'],
            'channel_pageflags'   => intval($pageflags),
            'channel_system'      => intval($system),
            'channel_expire_days' => intval($expire),
            'channel_timezone'    => App::$timezone
        ]
    );

    $r = q(
        "select * from channel where channel_account_id = %d
		and channel_guid = '%s' limit 1",
        intval($arr['account_id']),
        dbesc($guid)
    );

    if (! $r) {
        $ret['message'] = t('Unable to retrieve created identity');
        return $ret;
    }

    $a = q(
        "select * from account where account_id = %d",
        intval($arr['account_id'])
    );

    $photo_type = null;

    $z = [
            'account' => $a[0],
            'channel' => $r[0],
            'photo_url' => ''
    ];
    /**
     * @hooks create_channel_photo
     *   * \e array \b account
     *   * \e array \b channel
     *   * \e string \b photo_url - Return value
     */
    call_hooks('create_channel_photo', $z);

    if ($z['photo_url']) {
        $photo_type = import_channel_photo_from_url($z['photo_url'], $arr['account_id'], $r[0]['channel_id']);
    }

    if ($role_permissions && array_key_exists('limits', $role_permissions)) {
        $perm_limits = $role_permissions['limits'];
    } else {
        $perm_limits = site_default_perms();
    }

    foreach ($perm_limits as $p => $v) {
        PermissionLimits::Set($r[0]['channel_id'], $p, $v);
    }

    if ($role_permissions && array_key_exists('perms_auto', $role_permissions)) {
        set_pconfig($r[0]['channel_id'], 'system', 'autoperms', intval($role_permissions['perms_auto']));
    }

    $ret['channel'] = $r[0];

    if (intval($arr['account_id'])) {
        set_default_login_identity($arr['account_id'], $ret['channel']['channel_id'], false);
    }

    // Create a verified hub location pointing to this site.

    $r = hubloc_store_lowlevel(
        [
            'hubloc_guid'     => $guid,
            'hubloc_guid_sig' => $sig,
            'hubloc_id_url'   => (($system) ? z_root() : channel_url($ret['channel'])),
            'hubloc_hash'     => $hash,
            'hubloc_addr'     => channel_reddress($ret['channel']),
            'hubloc_primary'  => intval($primary),
            'hubloc_url'      => z_root(),
            'hubloc_url_sig'  => Libzot::sign(z_root(), $ret['channel']['channel_prvkey']),
            'hubloc_site_id'  => Libzot::make_xchan_hash(z_root(), get_config('system', 'pubkey')),
            'hubloc_host'     => App::get_hostname(),
            'hubloc_callback' => z_root() . '/zot',
            'hubloc_sitekey'  => get_config('system', 'pubkey'),
            'hubloc_network'  => 'nomad',
            'hubloc_updated'  => datetime_convert()
        ]
    );
    if (! $r) {
        logger('Unable to store hub location');
    }

    $newuid = $ret['channel']['channel_id'];

    $r = xchan_store_lowlevel(
        [
            'xchan_hash'       => $hash,
            'xchan_guid'       => $guid,
            'xchan_guid_sig'   => $sig,
            'xchan_pubkey'     => $key['pubkey'],
            'xchan_photo_mimetype' => (($photo_type) ? $photo_type : 'image/png'),
            'xchan_photo_l'    => z_root() . "/photo/profile/l/{$newuid}",
            'xchan_photo_m'    => z_root() . "/photo/profile/m/{$newuid}",
            'xchan_photo_s'    => z_root() . "/photo/profile/s/{$newuid}",
            'xchan_addr'       => channel_reddress($ret['channel']),
            'xchan_url'        => (($system) ? z_root() : channel_url($ret['channel'])),
            'xchan_follow'     => z_root() . '/follow?f=&url=%s',
            'xchan_connurl'    => z_root() . '/poco/' . $ret['channel']['channel_address'],
            'xchan_name'       => $ret['channel']['channel_name'],
            'xchan_network'    => 'nomad',
            'xchan_updated'    => datetime_convert(),
            'xchan_photo_date' => datetime_convert(),
            'xchan_name_date'  => datetime_convert(),
            'xchan_system'     => $system
        ]
    );

    // Not checking return value.
    // It's ok for this to fail if it's an imported channel, and therefore the hash is a duplicate

    $r = profile_store_lowlevel(
        [
            'aid'          => intval($ret['channel']['channel_account_id']),
            'uid'          => intval($newuid),
            'profile_guid' => new_uuid(),
            'profile_name' => t('Default Profile'),
            'is_default'   => 1,
            'publish'      => $publish,
            'fullname'     => $ret['channel']['channel_name'],
            'photo'        => z_root() . "/photo/profile/l/{$newuid}",
            'thumb'        => z_root() . "/photo/profile/m/{$newuid}"
        ]
    );

    if ($role_permissions) {
        $myperms = ((array_key_exists('perms_connect', $role_permissions)) ? $role_permissions['perms_connect'] : [] );
    } else {
        $x = PermissionRoles::role_perms('social');
        $myperms = $x['perms_connect'];
    }

    $r = abook_store_lowlevel(
        [
            'abook_account'   => intval($ret['channel']['channel_account_id']),
            'abook_channel'   => intval($newuid),
            'abook_xchan'     => $hash,
            'abook_closeness' => 0,
            'abook_created'   => datetime_convert(),
            'abook_updated'   => datetime_convert(),
            'abook_self'      => 1
        ]
    );


    $x = Permissions::serialise(Permissions::FilledPerms($myperms));
    set_abconfig($newuid, $hash, 'system', 'my_perms', $x);

    if (intval($ret['channel']['channel_account_id'])) {
        // Save our permissions role so we can perhaps call it up and modify it later.

        if ($role_permissions) {
            set_pconfig($newuid, 'system', 'permissions_role', $arr['permissions_role']);
            if (array_key_exists('online', $role_permissions)) {
                set_pconfig($newuid, 'system', 'hide_presence', 1 - intval($role_permissions['online']));
            }
            if (array_key_exists('perms_auto', $role_permissions)) {
                $autoperms = intval($role_permissions['perms_auto']);
                set_pconfig($newuid, 'system', 'autoperms', $autoperms);
            }
        }

        // Create a group with yourself as a member. This allows somebody to use it
        // right away as a default group for new contacts.

        $group_hash = AccessList::add($newuid, t('Friends'));
        if ($group_hash) {
            AccessList::member_add($newuid, t('Friends'), $ret['channel']['channel_hash']);

            // if our role_permissions indicate that we're using a default collection ACL, add it.

            if (is_array($role_permissions) && $role_permissions['default_collection']) {
                $default_collection_str = '<' . $group_hash . '>';
            }
            q(
                "update channel set channel_default_group = '%s', channel_allow_gid = '%s' where channel_id = %d",
                dbesc($group_hash),
                dbesc(($default_collection_str) ? $default_collection_str : EMPTY_STR),
                intval($newuid)
            );
        }

        set_pconfig($ret['channel']['channel_id'], 'system', 'photo_path', '%Y/%Y-%m');
        set_pconfig($ret['channel']['channel_id'], 'system', 'attach_path', '%Y/%Y-%m');

        // If this channel has a parent, auto follow them.

        if ($parent_channel_hash) {
            $ch = channelx_by_hash($parent_channel_hash);
            if ($ch) {
                connect_and_sync($ret['channel'], channel_reddress($ch), true);
            }
        }

        // auto-follow any of the hub's pre-configured channel choices.
        // Only do this if it's the first channel for this account;
        // otherwise it could get annoying. Don't make this list too big
        // or it will impact registration time.

        $accts = get_config('system', 'auto_follow');
        if (($accts) && (! $total_identities)) {
            if (! is_array($accts)) {
                $accts = array($accts);
            }

            foreach ($accts as $acct) {
                if (trim($acct)) {
                    $f = connect_and_sync($ret['channel'], trim($acct));
                    if ($f['success']) {
                        $can_view_stream = their_perms_contains($ret['channel']['channel_id'], $f['abook']['abook_xchan'], 'view_stream');

                        // If we can view their stream, pull in some posts

                        if (($can_view_stream) || ($f['abook']['xchan_network'] === 'rss')) {
                            Run::Summon([ 'Onepoll',$f['abook']['abook_id'] ]);
                        }
                    }
                }
            }
        }

        /**
         * @hooks create_identity
         *   Called when creating a channel.
         *   * \e int - The UID of the created identity
         */

        call_hooks('create_identity', $newuid);

        Run::Summon([ 'Directory', $ret['channel']['channel_id'] ]);
    }

    $ret['success'] = true;
    return $ret;
}



function connect_and_sync($channel, $address, $sub_channel = false)
{

    if ((! $channel) || (! $address)) {
        return false;
    }

    $f = Connect::connect($channel, $address, $sub_channel);
    if ($f['success']) {
        $clone = [];
        foreach ($f['abook'] as $k => $v) {
            if (strpos($k, 'abook_') === 0) {
                $clone[$k] = $v;
            }
        }
        unset($clone['abook_id']);
        unset($clone['abook_account']);
        unset($clone['abook_channel']);

        $abconfig = load_abconfig($channel['channel_id'], $clone['abook_xchan']);
        if ($abconfig) {
            $clone['abconfig'] = $abconfig;
        }

        Libsync::build_sync_packet($channel['channel_id'], [ 'abook' => [ $clone ] ], true);
        return $f;
    }
    return false;
}


function change_channel_keys($channel)
{

    $ret = array('success' => false);

    $stored = [];

    $key = Crypto::new_keypair(4096);

    $sig = Libzot::sign($channel['channel_guid'], $key['prvkey']);
    $hash = Libzot::make_xchan_hash($channel['channel_guid'], $channel['channel_pubkey']);

    $stored['old_guid']     = $channel['channel_guid'];
    $stored['old_guid_sig'] = $channel['channel_guid_sig'];
    $stored['old_key']      = $channel['channel_pubkey'];
    $stored['old_hash']     = $channel['channel_hash'];

    $stored['new_key']      = $key['pubkey'];
    $stored['new_sig']      = Libzot::sign($key['pubkey'], $channel['channel_prvkey']);

    // Save this info for the notifier to collect

    set_pconfig($channel['channel_id'], 'system', 'keychange', $stored);

    $r = q(
        "update channel set channel_prvkey = '%s', channel_pubkey = '%s', channel_guid_sig = '%s', channel_hash = '%s' where channel_id = %d",
        dbesc($key['prvkey']),
        dbesc($key['pubkey']),
        dbesc($sig),
        dbesc($hash),
        intval($channel['channel_id'])
    );
    if (! $r) {
        return $ret;
    }

    $r = q(
        "select * from channel where channel_id = %d",
        intval($channel['channel_id'])
    );

    if (! $r) {
        $ret['message'] = t('Unable to retrieve modified identity');
        return $ret;
    }

    $modified = $r[0];

    $h = q(
        "select * from hubloc where hubloc_hash = '%s' and hubloc_url = '%s' ",
        dbesc($stored['old_hash']),
        dbesc(z_root())
    );

    if ($h) {
        foreach ($h as $hv) {
            $hv['hubloc_guid_sig'] = $sig;
            $hv['hubloc_hash']     = $hash;
            $hv['hubloc_url_sig']  = Libzot::sign(z_root(), $modifed['channel_prvkey']);
            hubloc_store_lowlevel($hv);
        }
    }

    $x = q(
        "select * from xchan where xchan_hash = '%s' ",
        dbesc($stored['old_hash'])
    );

    $check = q(
        "select * from xchan where xchan_hash = '%s'",
        dbesc($hash)
    );

    if (($x) && (! $check)) {
        $oldxchan = $x[0];
        foreach ($x as $xv) {
            $xv['xchan_guid_sig']  = $sig;
            $xv['xchan_hash']      = $hash;
            $xv['xchan_pubkey']    = $key['pubkey'];
            $xv['xchan_updated']   = datetime_convert();
            xchan_store_lowlevel($xv);
            $newxchan = $xv;
        }
    }

    Libsync::build_sync_packet($channel['channel_id'], [ 'keychange' => $stored ]);

    $a = q(
        "select * from abook where abook_xchan = '%s' and abook_self = 1",
        dbesc($stored['old_hash'])
    );

    if ($a) {
        q(
            "update abook set abook_xchan = '%s' where abook_id = %d",
            dbesc($hash),
            intval($a[0]['abook_id'])
        );
    }

    xchan_change_key($oldxchan, $newxchan, $stored);

    Run::Summon([ 'Notifier', 'keychange', $channel['channel_id'] ]);

    $ret['success'] = true;
    return $ret;
}

function channel_change_address($channel, $new_address)
{

    $ret = array('success' => false);

    $old_address = $channel['channel_address'];

    if ($new_address === 'sys') {
        $ret['message'] = t('Reserved nickname. Please choose another.');
        return $ret;
    }

    if (check_webbie(array($new_address)) !== $new_address) {
        $ret['message'] = t('Nickname has unsupported characters or is already being used on this site.');
        return $ret;
    }

    $r = q(
        "update channel set channel_address = '%s' where channel_id = %d",
        dbesc($new_address),
        intval($channel['channel_id'])
    );
    if (! $r) {
        return $ret;
    }

    $r = q(
        "select * from channel where channel_id = %d",
        intval($channel['channel_id'])
    );

    if (! $r) {
        $ret['message'] = t('Unable to retrieve modified identity');
        return $ret;
    }

    $r = q(
        "update xchan set xchan_addr = '%s' where xchan_hash = '%s'",
        dbesc($new_address . '@' . App::get_hostname()),
        dbesc($channel['channel_hash'])
    );

    $h = q(
        "select * from hubloc where hubloc_hash = '%s' and hubloc_url = '%s' ",
        dbesc($channel['channel_hash']),
        dbesc(z_root())
    );

    if ($h) {
        foreach ($h as $hv) {
            if ($hv['hubloc_primary']) {
                q(
                    "update hubloc set hubloc_primary = 0 where hubloc_id = %d",
                    intval($hv['hubloc_id'])
                );
            }
            q(
                "update hubloc set hubloc_deleted = 1 where hubloc_id = %d",
                intval($hv['hubloc_id'])
            );

            unset($hv['hubloc_id']);
            $hv['hubloc_addr'] = $new_address . '@' . App::get_hostname();
            hubloc_store_lowlevel($hv);
        }
    }

    // fix apps which were stored with the actual name rather than a macro

    $r = q(
        "select * from app where app_channel = %d and app_system = 1",
        intval($channel['channel_id'])
    );
    if ($r) {
        foreach ($r as $rv) {
            $replace = preg_replace('/([\=\/])(' . $old_address . ')($|[\%\/])/ism', '$1' . $new_address . '$3', $rv['app_url']);
            if ($replace != $rv['app_url']) {
                q(
                    "update app set app_url = '%s' where id = %d",
                    dbesc($replace),
                    intval($rv['id'])
                );
            }
        }
    }

    Run::Summon([ 'Notifier', 'refresh_all', $channel['channel_id'] ]);

    $ret['success'] = true;
    return $ret;
}


/**
 * @brief Set default channel to be used on login.
 *
 * @param int $account_id
 *       login account
 * @param int $channel_id
 *       channel id to set as default for this account
 * @param bool $force (optional) default true
 *       - if true, set this default unconditionally
 *       - if $force is false only do this if there is no existing default
 */
function set_default_login_identity($account_id, $channel_id, $force = true)
{
    $r = q(
        "select account_default_channel from account where account_id = %d limit 1",
        intval($account_id)
    );
    if ($r) {
        if ((intval($r[0]['account_default_channel']) == 0) || ($force)) {
            $r = q(
                "update account set account_default_channel = %d where account_id = %d",
                intval($channel_id),
                intval($account_id)
            );
        }
    }
}

/**
 * @brief Return an array with default list of sections to export.
 *
 * @return array with default section names to export
 */
function get_default_export_sections()
{
    $sections = [
            'channel',
            'connections',
            'config',
            'apps',
            'chatrooms',
            'events'
    ];

    $cb = [ 'sections' => $sections ];
    /**
     * @hooks get_default_export_sections
     *   Called to get the default list of functional data groups to export in identity_basic_export().
     *   * \e array \b sections - return value
     */
    call_hooks('get_default_export_sections', $cb);

    return $cb['sections'];
}


/**
 * @brief Create an array representing the important channel information
 * which would be necessary to create a nomadic identity clone. This includes
 * most channel resources and connection information with the exception of content.
 *
 * @param int $channel_id
 *     Channel_id to export
 * @param array $sections (optional)
 *     Which sections to include in the export, default see get_default_export_sections()
 * @return array
 *     See function for details
 */
function identity_basic_export($channel_id, $sections = null)
{

    /*
     * basic channel export
     */

    if (! $sections) {
        $sections = get_default_export_sections();
    }

    $ret = [];

    // use constants here as otherwise we will have no idea if we can import from a site
    // with a non-standard platform and version.

    $ret['compatibility'] = [
        'project'     => PLATFORM_NAME,
        'codebase'    => 'zap',
        'version'     => STD_VERSION,
        'database'    => DB_UPDATE_VERSION
    ];

    /*
     * Process channel information regardless of it is one of the sections desired
     * because we need the channel relocation information in all export files/streams.
     */

    $r = q(
        "select * from channel where channel_id = %d limit 1",
        intval($channel_id)
    );
    if ($r) {
        $ret['relocate'] = [ 'channel_address' => $r[0]['channel_address'], 'url' => z_root()];
        if (in_array('channel', $sections)) {
            $ret['channel'] = $r[0];
            unset($ret['channel']['channel_password']);
            unset($ret['channel']['channel_salt']);
        }
    }

    if (in_array('channel', $sections) || in_array('profile', $sections)) {
        $r = q(
            "select * from profile where uid = %d",
            intval($channel_id)
        );
        if ($r) {
            $ret['profile'] = $r;
        }

        $r = q(
            "select mimetype, content, os_storage from photo
			where imgscale = 4 and photo_usage = %d and uid = %d limit 1",
            intval(PHOTO_PROFILE),
            intval($channel_id)
        );

        if ($r) {
            $ret['photo'] = [
                'type' => $r[0]['mimetype'],
                'data' => (($r[0]['os_storage'])
                    ? base64url_encode(file_get_contents($r[0]['content'])) : base64url_encode(dbunescbin($r[0]['content'])))
            ];
        }
    }

    if (in_array('connections', $sections)) {
        $r = q(
            "select * from atoken where atoken_uid = %d",
            intval($channel_id)
        );
        if ($r) {
            $ret['atoken'] = $r;
        }

        $xchans = [];
        $r = q(
            "select * from abook where abook_channel = %d ",
            intval($channel_id)
        );
        if ($r) {
            $ret['abook'] = $r;

            for ($x = 0; $x < count($ret['abook']); $x++) {
                $xchans[] = $ret['abook'][$x]['abook_xchan'];
                $abconfig = load_abconfig($channel_id, $ret['abook'][$x]['abook_xchan']);
                if ($abconfig) {
                    $ret['abook'][$x]['abconfig'] = $abconfig;
                }
            }
            stringify_array_elms($xchans);
        }

        if ($xchans) {
            $r = q("select * from xchan where xchan_hash in ( " . implode(',', $xchans) . " ) ");
            if ($r) {
                $ret['xchan'] = $r;
            }

            $r = q("select * from hubloc where hubloc_hash in ( " . implode(',', $xchans) . " ) ");
            if ($r) {
                $ret['hubloc'] = $r;
            }
        }

        $r = q(
            "select * from pgrp where uid = %d ",
            intval($channel_id)
        );

        if ($r) {
            $ret['group'] = $r;
        }

        $r = q(
            "select * from pgrp_member where uid = %d ",
            intval($channel_id)
        );
        if ($r) {
            $ret['group_member'] = $r;
        }

        $r = q(
            "select * from xign where uid = %d ",
            intval($channel_id)
        );
        if ($r) {
            $ret['xign'] = $r;
        }

        $r = q(
            "select * from block where block_channel_id = %d ",
            intval($channel_id)
        );
        if ($r) {
            $ret['block'] = $r;
        }
    }

    if (in_array('config', $sections)) {
        $r = q(
            "select * from pconfig where uid = %d",
            intval($channel_id)
        );
        if ($r) {
            $ret['config'] = $r;
        }

        // All other term types will be included in items, if requested.

        $r = q(
            "select * from term where ttype in (%d,%d) and uid = %d",
            intval(TERM_SAVEDSEARCH),
            intval(TERM_THING),
            intval($channel_id)
        );
        if ($r) {
            $ret['term'] = $r;
        }
        // add psuedo-column obj_baseurl to aid in relocations

        $r = q(
            "select obj.*, '%s' as obj_baseurl from obj where obj_channel = %d",
            dbesc(z_root()),
            intval($channel_id)
        );

        if ($r) {
            $ret['obj'] = $r;
        }

        $r = q(
            "select * from likes where channel_id = %d",
            intval($channel_id)
        );

        if ($r) {
            $ret['likes'] = $r;
        }
    }

    if (in_array('apps', $sections)) {
        $r = q(
            "select * from app where app_channel = %d and app_system = 0",
            intval($channel_id)
        );
        if ($r) {
            for ($x = 0; $x < count($r); $x++) {
                $r[$x]['term'] = q(
                    "select * from term where otype = %d and oid = %d",
                    intval(TERM_OBJ_APP),
                    intval($r[$x]['id'])
                );
            }
            $ret['app'] = $r;
        }
        $r = q(
            "select * from app where app_channel = %d and app_system = 1",
            intval($channel_id)
        );
        if ($r) {
            for ($x = 0; $x < count($r); $x++) {
                $r[$x]['term'] = q(
                    "select * from term where otype = %d and oid = %d",
                    intval(TERM_OBJ_APP),
                    intval($r[$x]['id'])
                );
            }
            $ret['sysapp'] = $r;
        }
    }

    if (in_array('chatrooms', $sections)) {
        $r = q(
            "select * from chatroom where cr_uid = %d",
            intval($channel_id)
        );
        if ($r) {
            $ret['chatroom'] = $r;
        }
    }

    if (in_array('events', $sections)) {
        $r = q(
            "select * from event where uid = %d",
            intval($channel_id)
        );
        if ($r) {
            $ret['event'] = $r;
        }

        $r = q(
            "select * from item where resource_type = 'event' and uid = %d",
            intval($channel_id)
        );
        if ($r) {
            $ret['event_item'] = [];
            xchan_query($r);
            $r = fetch_post_tags($r, true);
            foreach ($r as $rr) {
                $ret['event_item'][] = encode_item($rr, true);
            }
        }
    }

    if (in_array('items', $sections)) {
        /** @warning this may run into memory limits on smaller systems */

        /** export three months of posts. If you want to export and import all posts you have to start with
         * the first year and export/import them in ascending order.
         *
         * Don't export linked resource items. we'll have to pull those out separately.
         */

        $r = q(
            "select * from item where item_wall = 1 and item_deleted = 0 and uid = %d
			and created > %s - INTERVAL %s and resource_type = '' order by created",
            intval($channel_id),
            db_utcnow(),
            db_quoteinterval('3 MONTH')
        );
        if ($r) {
            $ret['item'] = [];
            xchan_query($r);
            $r = fetch_post_tags($r, true);
            foreach ($r as $rr) {
                $ret['item'][] = encode_item($rr, true);
            }
        }
    }

    $addon = [
            'channel_id' => $channel_id,
            'sections' => $sections,
            'data' => $ret
    ];
    /**
     * @hooks identity_basic_export
     *   Called when exporting a channel's basic information for backup or transfer.
     *   * \e number \b channel_id - The channel ID
     *   * \e array \b sections
     *   * \e array \b data - The data will get returned
     */
    call_hooks('identity_basic_export', $addon);
    $ret = $addon['data'];

    return $ret;
}

/**
 * @brief Export items for a year, or a month of a year.
 *
 * @param int $channel_id The channel ID
 * @param number $year YYYY
 * @param number $month (optional) 0-12, default 0 complete year
 * @return array An associative array
 *   * \e array \b relocate - (optional)
 *   * \e array \b item - array with items encoded_item()
 */
function identity_export_year($channel_id, $year, $month = 0)
{

    if (! $year) {
        return [];
    }

    if ($month && $month <= 12) {
        $target_month = sprintf('%02d', $month);
        $target_month_plus = sprintf('%02d', $month + 1);
    } else {
        $target_month = '01';
    }

    $mindate = datetime_convert('UTC', 'UTC', $year . '-' . $target_month . '-01 00:00:00');
    if ($month && $month < 12) {
        $maxdate = datetime_convert('UTC', 'UTC', $year . '-' . $target_month_plus . '-01 00:00:00');
    } else {
        $maxdate = datetime_convert('UTC', 'UTC', $year + 1 . '-01-01 00:00:00');
    }

    return channel_export_items_date($channel_id, $mindate, $maxdate);
}

/**
 * @brief Export items within an arbitrary date range.
 *
 * Date/time is in UTC.
 *
 * @param int $channel_id The channel ID
 * @param string $start
 * @param string $finish
 * @return array
 */

function channel_export_items_date($channel_id, $start, $finish)
{

    if (! $start) {
        return [];
    } else {
        $start = datetime_convert('UTC', 'UTC', $start);
    }

    $finish = datetime_convert('UTC', 'UTC', (($finish) ? $finish : 'now'));
    if ($finish < $start) {
        return [];
    }

    $ret = [];

    $ch = channelx_by_n($channel_id);
    if ($ch) {
        $ret['relocate'] = [ 'channel_address' => $ch['channel_address'], 'url' => z_root()];
    }

    $ret['compatibility']['codebase'] = 'zap';

    $r = q(
        "select * from item where ( item_wall = 1 or item_type != %d ) and item_deleted = 0 and uid = %d and created >= '%s' and created <= '%s'  and resource_type = '' order by created",
        intval(ITEM_TYPE_POST),
        intval($channel_id),
        dbesc($start),
        dbesc($finish)
    );

    if ($r) {
        $ret['item'] = [];
        xchan_query($r);
        $r = fetch_post_tags($r, true);
        foreach ($r as $rr) {
            $ret['item'][] = encode_item($rr, true);
        }
    }

    return $ret;
}



/**
 * @brief Export items with pagination
 *
 *
 * @param int $channel_id The channel ID
 * @param int $page
 * @param int $limit (default 50)
 * @return array
 */

function channel_export_items_page($channel_id, $start, $finish, $page = 0, $limit = 50)
{

    if (intval($page) < 1) {
        $page = 0;
    }

    if (intval($limit) < 1) {
        $limit = 1;
    }

    if (intval($limit) > 5000) {
        $limit = 5000;
    }

    if (! $start) {
        $start = NULL_DATE;
    } else {
        $start = datetime_convert('UTC', 'UTC', $start);
    }

    $finish = datetime_convert('UTC', 'UTC', (($finish) ? $finish : 'now'));
    if ($finish < $start) {
        return [];
    }

    $offset = intval($limit) * intval($page);

    $ret = [];

    $ch = channelx_by_n($channel_id);
    if ($ch) {
        $ret['relocate'] = [ 'channel_address' => $ch['channel_address'], 'url' => z_root()];
    }

    $ret['compatibility']['codebase'] = 'zap';


    $r = q(
        "select * from item where ( item_wall = 1 or item_type != %d ) and item_deleted = 0 and uid = %d and resource_type = '' and created >= '%s' and created <= '%s' order by created limit %d offset %d",
        intval(ITEM_TYPE_POST),
        intval($channel_id),
        dbesc($start),
        dbesc($finish),
        intval($limit),
        intval($offset)
    );

    if ($r) {
        $ret['item'] = [];
        xchan_query($r);
        $r = fetch_post_tags($r, true);
        foreach ($r as $rr) {
            $ret['item'][] = encode_item($rr, true);
        }
    }

    return $ret;
}



function get_my_url()
{
    if (x($_SESSION, 'zrl_override')) {
        return $_SESSION['zrl_override'];
    }
    if (x($_SESSION, 'my_url')) {
        return $_SESSION['my_url'];
    }

    return false;
}

function get_my_address()
{
    if (x($_SESSION, 'zid_override')) {
        return $_SESSION['zid_override'];
    }
    if (x($_SESSION, 'my_address')) {
        return $_SESSION['my_address'];
    }

    return false;
}

/**
 * @brief Add visitor's zid to our xchan and attempt authentication.
 *
 * If somebody arrives at our site using a zid, add their xchan to our DB if we
 * don't have it already.
 * And if they aren't already authenticated here, attempt reverse magic auth.
 */
function zid_init()
{
    $tmp_str = get_my_address();
    if (validate_email($tmp_str)) {
        $arr = [
                'zid' => $tmp_str,
                'url' => App::$cmd
        ];
        /**
        * @hooks zid_init
        *   * \e string \b zid - their zid
        *   * \e string \b url - the destination url
        */
        call_hooks('zid_init', $arr);

        if (! local_channel()) {
            $r = q(
                "select * from hubloc where hubloc_addr = '%s' order by hubloc_connected desc limit 1",
                dbesc($tmp_str)
            );
            if (! $r) {
                Run::Summon([ 'Gprobe', bin2hex($tmp_str) ]);
            }
            if ($r && remote_channel() && remote_channel() === $r[0]['hubloc_hash']) {
                return;
            }

            logger('Not authenticated. Invoking reverse magic-auth for ' . $tmp_str);
            // try to avoid recursion - but send them home to do a proper magic auth
            $query = App::$query_string;
            $query = str_replace(array('?zid=','&zid='), array('?rzid=','&rzid='), $query);
            $dest = '/' . $query;
            if ($r && ($r[0]['hubloc_url'] != z_root()) && (! strstr($dest, '/magic')) && (! strstr($dest, '/rmagic'))) {
                goaway($r[0]['hubloc_url'] . '/magic' . '?f=&rev=1&owa=1&bdest=' . bin2hex(z_root() . $dest));
            } else {
                logger('No hubloc found.');
            }
        }
    }
}

/**
 * @brief If somebody arrives at our site using a zat, authenticate them.
 *
 */
function zat_init()
{
    if (local_channel() || remote_channel()) {
        return;
    }

    $r = q(
        "select * from atoken where atoken_token = '%s' limit 1",
        dbesc($_REQUEST['zat'])
    );
    if ($r) {
        $xchan = atoken_xchan($r[0]);
//      atoken_create_xchan($xchan);
        atoken_login($xchan);
    }
}

function atoken_delete_and_sync($channel_id, $atoken_guid)
{
    $r = q(
        "select * from atoken where atoken_guid = '%s' and atoken_uid = %d",
        dbesc($atoken_guid),
        intval($channel_id)
    );
    if ($r) {
        $atok = array_shift($r);
        $atok['deleted'] = true;
        atoken_delete($atok['atoken_id']);
        Libsync::build_sync_packet($channel_id, [ 'atoken' => [ $atok ] ]);
    }
}

/**
 * @brief Used from within PCSS themes to set theme parameters.
 *
 * If there's a puid request variable, that is the "page owner" and normally
 * their theme settings take precedence; unless a local user sets the "always_my_theme"
 * system pconfig, which means they don't want to see anybody else's theme
 * settings except their own while on this site.
 *
 * @return int
 */
function get_theme_uid()
{
    $uid = ((isset($_REQUEST['puid'])) ? intval($_REQUEST['puid']) : 0);
    if (local_channel()) {
        if ((get_pconfig(local_channel(), 'system', 'always_my_theme')) || (! $uid)) {
            return local_channel();
        }
    }
    if (! $uid) {
        $x = get_sys_channel();
        if ($x) {
            return $x['channel_id'];
        }
    }

    return $uid;
}

/**
* @brief Retrieves the path of the default_profile_photo for this system
* with the specified size.
*
* @param int $size (optional) default 300
*   one of (300, 80, 48)
* @return string with path to profile photo
*/
function get_default_profile_photo($size = 300)
{
    $scheme = get_config('system', 'default_profile_photo');
    if (! $scheme) {
        $scheme = 'rainbow_man';
    }

    if (! is_dir('images/default_profile_photos/' . $scheme)) {
        $x = [ 'scheme' => $scheme, 'size' => $size, 'url' => '' ];
        call_hooks('default_profile_photo', $x);
        if ($x['url']) {
            return $x['url'];
        } else {
            $scheme = 'rainbow_man';
        }
    }

    return 'images/default_profile_photos/' . $scheme . '/' . $size . '.png';
}

/**
 * @brief Test whether a given identity is NOT a member of the Hubzilla.
 *
 * @param string $s
 *    xchan_hash of the identity in question
 * @return bool true or false
 */
function is_foreigner($s)
{
    return((strpbrk($s, '.:@')) ? true : false);
}

/**
 * @brief Test whether a given identity is a member of the Hubzilla.
 *
 * @param string $s
 *    xchan_hash of the identity in question
 * @return bool true or false
 */
function is_member($s)
{
    return((is_foreigner($s)) ? false : true);
}

/**
 * @brief Get chatpresence status for nick.
 *
 * @param string $nick
 * @return array An associative array with
 *   * \e bool \b result
 */
function get_online_status($nick)
{

    $ret = array('result' => false);

    if (observer_prohibited()) {
        return $ret;
    }

    $r = q(
        "select channel_id, channel_hash from channel where channel_address = '%s' limit 1",
        dbesc($nick)
    );
    if ($r) {
        $hide = get_pconfig($r[0]['channel_id'], 'system', 'hide_online_status');
        if ($hide) {
            return $ret;
        }
        $x = q(
            "select cp_status from chatpresence where cp_xchan = '%s' and cp_room = 0 limit 1",
            dbesc($r[0]['channel_hash'])
        );
        if ($x) {
            $ret['result'] = $x[0]['cp_status'];
        }
    }

    return $ret;
}


/**
 * @brief
 *
 * @param string $webbie
 * @return array|bool|string
 */
function remote_online_status($webbie)
{

    $result = false;
    $r = q(
        "select * from hubloc where hubloc_addr = '%s' limit 1",
        dbesc($webbie)
    );
    if (! $r) {
        return $result;
    }
    $url = $r[0]['hubloc_url'] . '/online/' . substr($webbie, 0, strpos($webbie, '@'));

    $x = z_fetch_url($url);
    if ($x['success']) {
        $j = json_decode($x['body'], true);
        if ($j) {
            $result = (($j['result']) ? $j['result'] : false);
        }
    }

    return $result;
}


/**
 * @brief Return the parsed identity selector HTML template.
 *
 * @return string with parsed HTML channel_id_selet template
 */
function identity_selector()
{
    if (local_channel()) {
        $r = q(
            "select channel.*, xchan.* from channel left join xchan on channel.channel_hash = xchan.xchan_hash where channel.channel_account_id = %d and channel_removed = 0 order by channel_name ",
            intval(get_account_id())
        );
        if ($r && count($r) > 1) {
            $o = replace_macros(get_markup_template('channel_id_select.tpl'), array(
                '$channels' => $r,
                '$selected' => local_channel()
            ));

            return $o;
        }
    }

    return '';
}


function is_public_profile()
{
    if (! local_channel()) {
        return false;
    }

    if (intval(get_config('system', 'block_public'))) {
        return false;
    }

    $channel = App::get_channel();
    if ($channel) {
        $perm = PermissionLimits::Get($channel['channel_id'], 'view_profile');
        if ($perm == PERMS_PUBLIC) {
            return true;
        }
    }

    return false;
}

function get_profile_fields_basic($filter = 0)
{

    $profile_fields_basic = (($filter == 0) ? get_config('system', 'profile_fields_basic') : null);

    if (! $profile_fields_basic) {
        $profile_fields_basic = array('fullname','pdesc','chandesc','basic_gender','pronouns','dob','dob_tz','region','country_name','marital','sexual','homepage','hometown','keywords','about','contact');
    }

    $x = [];
    if ($profile_fields_basic) {
        foreach ($profile_fields_basic as $f) {
            $x[$f] = 1;
        }
    }

    return $x;
}


function get_profile_fields_advanced($filter = 0)
{
    $basic = get_profile_fields_basic($filter);
    $profile_fields_advanced = (($filter == 0) ? get_config('system', 'profile_fields_advanced') : null);
    if (! $profile_fields_advanced) {
        $profile_fields_advanced = array('comms', 'address','locality','postal_code','advanced_gender', 'partner','howlong','politic','religion','likes','dislikes','interest','channels','music','book','film','tv','romance','employment','education');
    }
    $x = [];
    if ($basic) {
        foreach ($basic as $f => $v) {
            $x[$f] = $v;
        }
    }

    if ($profile_fields_advanced) {
        foreach ($profile_fields_advanced as $f) {
            $x[$f] = 1;
        }
    }

    return $x;
}

/**
 * @brief Clear notifyflags for a channel.
 *
 * Most likely during bulk import of content or other activity that is likely
 * to generate huge amounts of undesired notifications.
 *
 * @param int $channel_id
 *    The channel to disable notifications for
 * @return int
 *    Current notification flag value. Send this to notifications_on() to restore the channel settings when finished
 *    with the activity requiring notifications_off();
 */
function notifications_off($channel_id)
{
    $r = q(
        "select channel_notifyflags from channel where channel_id = %d limit 1",
        intval($channel_id)
    );
    q(
        "update channel set channel_notifyflags = 0 where channel_id = %d",
        intval($channel_id)
    );

    return intval($r[0]['channel_notifyflags']);
}


function notifications_on($channel_id, $value)
{
    $x = q(
        "update channel set channel_notifyflags = %d where channel_id = %d",
        intval($value),
        intval($channel_id)
    );

    return $x;
}


function get_channel_default_perms($uid)
{

    $ret = [];

    $r = q(
        "select abook_xchan from abook where abook_channel = %d and abook_self = 1 limit 1",
        intval($uid)
    );
    if ($r) {
        $ret = Permissions::FilledPerms(get_abconfig($uid, $r[0]['abook_xchan'], 'system', 'my_perms', EMPTY_STR));
    }

    return $ret;
}


function profiles_build_sync($channel_id, $send = true)
{
    $r = q(
        "select * from profile where uid = %d",
        intval($channel_id)
    );
    if ($r) {
        if ($send) {
            Libsync::build_sync_packet($channel_id, array('profile' => $r));
        } else {
            return $r;
        }
    }
}


function auto_channel_create($account_id)
{

    if (! $account_id) {
        return false;
    }

    $arr = [];
    $arr['account_id'] = $account_id;
    $arr['name'] = get_aconfig($account_id, 'register', 'channel_name');
    $arr['nickname'] = legal_webbie(get_aconfig($account_id, 'register', 'channel_address'));
    $arr['permissions_role'] = get_aconfig($account_id, 'register', 'permissions_role');

    del_aconfig($account_id, 'register', 'channel_name');
    del_aconfig($account_id, 'register', 'channel_address');
    del_aconfig($account_id, 'register', 'permissions_role');

    if ((! $arr['name']) || (! $arr['nickname'])) {
        $x = q(
            "select * from account where account_id = %d limit 1",
            intval($account_id)
        );
        if ($x) {
            if (! $arr['name']) {
                $arr['name'] = substr($x[0]['account_email'], 0, strpos($x[0]['account_email'], '@'));
            }
            if (! $arr['nickname']) {
                $arr['nickname'] = legal_webbie(substr($x[0]['account_email'], 0, strpos($x[0]['account_email'], '@')));
            }
        }
    }
    if (! $arr['permissions_role']) {
        $arr['permissions_role'] = 'social';
    }

    if (validate_channelname($arr['name'])) {
        return false;
    }
    if ($arr['nickname'] === 'sys') {
        $arr['nickname'] = $arr['nickname'] . mt_rand(1000, 9999);
    }

    $arr['nickname'] = check_webbie(array($arr['nickname'], $arr['nickname'] . mt_rand(1000, 9999)));

    return create_identity($arr);
}

function get_cover_photo($channel_id, $format = 'bbcode', $res = PHOTO_RES_COVER_1200)
{

    $r = q(
        "select height, width, resource_id, edited, mimetype from photo where uid = %d and imgscale = %d and photo_usage = %d",
        intval($channel_id),
        intval($res),
        intval(PHOTO_COVER)
    );
    if (! $r) {
        return false;
    }

    $output = false;

    $url = z_root() . '/photo/' . $r[0]['resource_id'] . '-' . $res ;

    switch ($format) {
        case 'bbcode':
            $output = '[zrl=' . $r[0]['width'] . 'x' . $r[0]['height'] . ']' . $url . '[/zrl]';
            break;
        case 'html':
            $output = '<img class="zrl" width="' . $r[0]['width'] . '" height="' . $r[0]['height'] . '" src="' . $url . '" alt="' . t('cover photo') . '" />';
            break;
        case 'array':
        default:
            $output = array(
                'width' => $r[0]['width'],
                'height' => $r[0]['height'],
                'type' => $r[0]['mimetype'],
                'updated' => $r[0]['edited'],
                'url' => $url
            );
            break;
    }

    return $output;
}


/**
 * @brief Return parsed HTML zcard template.
 *
 * @param array $channel
 * @param string $observer_hash (optional)
 * @param array $args (optional)
 * @return string parsed HTML from \e zcard template
 */
function get_zcard($channel, $observer_hash = '', $args = [])
{

    logger('get_zcard');

    $maxwidth = (($args['width']) ? intval($args['width']) : 0);
    $maxheight = (($args['height']) ? intval($args['height']) : 0);

    if (($maxwidth > 1200) || ($maxwidth < 1)) {
        $maxwidth = 1200;
        $cover_width = 1200;
    }

    if ($maxwidth <= 425) {
        $width = 425;
        $cover_width = 425;
        $size = 'hz_small';
        $cover_size = PHOTO_RES_COVER_425;
        $pphoto = array('mimetype' => $channel['xchan_photo_mimetype'], 'width' => 80 , 'height' => 80, 'href' => $channel['xchan_photo_m'] . '?rev=' . strtotime($channel['xchan_photo_date']));
    } elseif ($maxwidth <= 900) {
        $width = 900;
        $cover_width = 850;
        $size = 'hz_medium';
        $cover_size = PHOTO_RES_COVER_850;
        $pphoto = array('mimetype' => $channel['xchan_photo_mimetype'], 'width' => 160 , 'height' => 160, 'href' => $channel['xchan_photo_l'] . '?rev=' . strtotime($channel['xchan_photo_date']));
    } elseif ($maxwidth <= 1200) {
        $width = 1200;
        $cover_width = 1200;
        $size = 'hz_large';
        $cover_size = PHOTO_RES_COVER_1200;
        $pphoto = array('mimetype' => $channel['xchan_photo_mimetype'], 'width' => 300 , 'height' => 300, 'href' => $channel['xchan_photo_l'] . '?rev=' . strtotime($channel['xchan_photo_date']));
    }

//  $scale = (float) $maxwidth / $width;
//  $translate = intval(($scale / 1.0) * 100);

    $channel['channel_addr'] = channel_reddress($channel);
    $zcard = array('chan' => $channel);

    $r = q(
        "select height, width, resource_id, imgscale, mimetype from photo where uid = %d and imgscale = %d and photo_usage = %d",
        intval($channel['channel_id']),
        intval($cover_size),
        intval(PHOTO_COVER)
    );

    if ($r) {
        $cover = $r[0];
        $cover['href'] = z_root() . '/photo/' . $r[0]['resource_id'] . '-' . $r[0]['imgscale'];
    } else {
        $default_cover = get_config('system', 'default_cover_photo', 'pexels-94622');
        $cover = [ 'href' => z_root() . '/images/default_cover_photos/' . $default_cover . '/' . $cover_width . '.jpg' ];
    }

    $o .= replace_macros(get_markup_template('zcard.tpl'), array(
        '$maxwidth' => $maxwidth,
        '$scale' => $scale,
        '$translate' => $translate,
        '$size' => $size,
        '$cover' => $cover,
        '$pphoto' => $pphoto,
        '$zcard' => $zcard
    ));

    return $o;
}


/**
 * @brief Return parsed HTML embed zcard template.
 *
 * @param array $channel
 * @param string $observer_hash (optional)
 * @param array $args (optional)
 * @return string parsed HTML from \e zcard_embed template
 */
function get_zcard_embed($channel, $observer_hash = '', $args = [])
{

    logger('get_zcard_embed');

    $maxwidth = (($args['width']) ? intval($args['width']) : 0);
    $maxheight = (($args['height']) ? intval($args['height']) : 0);

    if (($maxwidth > 1200) || ($maxwidth < 1)) {
        $maxwidth = 1200;
        $cover_width = 1200;
    }

    if ($maxwidth <= 425) {
        $width = 425;
        $cover_width = 425;
        $size = 'hz_small';
        $cover_size = PHOTO_RES_COVER_425;
        $pphoto = array('mimetype' => $channel['xchan_photo_mimetype'],  'width' => 80 , 'height' => 80, 'href' => $channel['xchan_photo_m']);
    } elseif ($maxwidth <= 900) {
        $width = 900;
        $cover_width = 850;
        $size = 'hz_medium';
        $cover_size = PHOTO_RES_COVER_850;
        $pphoto = array('mimetype' => $channel['xchan_photo_mimetype'],  'width' => 160 , 'height' => 160, 'href' => $channel['xchan_photo_l']);
    } elseif ($maxwidth <= 1200) {
        $width = 1200;
        $cover_width = 1200;
        $size = 'hz_large';
        $cover_size = PHOTO_RES_COVER_1200;
        $pphoto = array('mimetype' => $channel['xchan_photo_mimetype'],  'width' => 300 , 'height' => 300, 'href' => $channel['xchan_photo_l']);
    }

    $channel['channel_addr'] = channel_reddress($channel);
    $zcard = array('chan' => $channel);

    $r = q(
        "select height, width, resource_id, imgscale, mimetype from photo where uid = %d and imgscale = %d and photo_usage = %d",
        intval($channel['channel_id']),
        intval($cover_size),
        intval(PHOTO_COVER)
    );

    if ($r) {
        $cover = $r[0];
        $cover['href'] = z_root() . '/photo/' . $r[0]['resource_id'] . '-' . $r[0]['imgscale'];
    } else {
        $default_cover = get_config('system', 'default_cover_photo', 'pexels-94622');
        $cover = [ 'href' => z_root() . '/images/default_cover_photos/' . $default_cover . '/' . $cover_width . '.jpg' ];
    }

    $o .= replace_macros(get_markup_template('zcard_embed.tpl'), array(
        '$maxwidth' => $maxwidth,
        '$scale' => $scale,
        '$translate' => $translate,
        '$size' => $size,
        '$cover' => $cover,
        '$pphoto' => $pphoto,
        '$zcard' => $zcard
    ));

    return $o;
}

/**
 * @brief Get a channel array from a channel nickname.
 *
 * @param string $nick - A channel_address nickname
 * @return array|bool
 *   - array with channel entry
 *   - false if no channel with $nick was found
 */

function channelx_by_nick($nick, $include_removed = false)
{

    $sql_extra = (($include_removed) ? "" : " and channel_removed = 0 ");

    // If we are provided a Unicode nickname convert to IDN

    $nick = punify($nick);

    // return a cached copy if there is a cached copy and it's a match.
    // Also check that there is an xchan_hash to validate the App::$channel data is complete
    // and that columns from both joined tables are present

    if (
        App::$channel && is_array(App::$channel) && array_key_exists('channel_address', App::$channel)
        && array_key_exists('xchan_hash', App::$channel) && App::$channel['channel_address'] === $nick
    ) {
        return App::$channel;
    }

    $r = q(
        "SELECT * FROM channel left join xchan on channel_hash = xchan_hash WHERE channel_address = '%s' $sql_extra LIMIT 1",
        dbesc($nick)
    );

    return(($r) ? array_shift($r) : false);
}

/**
 * @brief Get a channel array by a channel_hash.
 *
 * @param string $hash
 * @return array|bool false if channel ID not found, otherwise the channel array
 */

function channelx_by_hash($hash, $include_removed = false)
{

    $sql_extra = (($include_removed) ? "" : " and channel_removed = 0 ");

    if (
        App::$channel && is_array(App::$channel) && array_key_exists('channel_hash', App::$channel)
        && array_key_exists('xchan_hash', App::$channel) && App::$channel['channel_hash'] === $hash
    ) {
        return App::$channel;
    }

    $r = q(
        "SELECT * FROM channel left join xchan on channel_hash = xchan_hash WHERE channel_hash = '%s' $sql_extra LIMIT 1",
        dbesc($hash)
    );

    return(($r) ? array_shift($r) : false);
}


/**
 * @brief Get a channel array by a channel ID.
 *
 * @param int $id A channel ID
 * @return array|bool false if channel ID not found, otherwise the channel array
 */

function channelx_by_n($id, $include_removed = false)
{

    $sql_extra = (($include_removed) ? "" : " and channel_removed = 0 ");

    if (
        App::$channel && is_array(App::$channel) && array_key_exists('channel_id', App::$channel)
        && array_key_exists('xchan_hash', App::$channel) && intval(App::$channel['channel_id']) === intval($id)
    ) {
        return App::$channel;
    }

    $r = q(
        "SELECT * FROM channel LEFT JOIN xchan ON channel_hash = xchan_hash WHERE channel_id = %d $sql_extra LIMIT 1",
        dbesc($id)
    );

    return(($r) ? array_shift($r) : false);
}

/**
 * @brief
 *
 * @param array $channel
 * @return string
 */

function channel_reddress($channel)
{
    if (! ($channel && array_key_exists('channel_address', $channel))) {
        return '';
    }

    return strtolower($channel['channel_address'] . '@' . App::get_hostname());
}


/**
 * @brief Get manual channel conversation update config.
 *
 * Check the channel config \e manual_conversation_update, if not set fall back
 * to global system config, defaults to 1 if nothing set.
 *
 * @param int $channel_id
 * @return int
 */

function channel_manual_conv_update($channel_id)
{

    $x = get_pconfig($channel_id, 'system', 'manual_conversation_update', get_config('system', 'manual_conversation_update', 1));
    return intval($x);
}


/**
 * @brief Return parsed HTML remote_login template.
 *
 * @return string with parsed HTML from \e remote_login template
 */
function remote_login()
{
    $o = replace_macros(get_markup_template('remote_login.tpl'), array(
        '$title' => t('Remote Authentication'),
        '$desc' => t('Enter your channel address (e.g. channel@example.com)'),
        '$submit' => t('Authenticate')
    ));

    return $o;
}

function channel_store_lowlevel($arr)
{
    $store = [
        'channel_account_id'      => ((array_key_exists('channel_account_id', $arr))      ? $arr['channel_account_id']      : '0'),
        'channel_primary'         => ((array_key_exists('channel_primary', $arr))         ? $arr['channel_primary']         : '0'),
        'channel_name'            => ((array_key_exists('channel_name', $arr))            ? $arr['channel_name']            : ''),
        'channel_parent'          => ((array_key_exists('channel_parent', $arr))          ? $arr['channel_parent']          : ''),
        'channel_address'         => ((array_key_exists('channel_address', $arr))         ? $arr['channel_address']         : ''),
        'channel_guid'            => ((array_key_exists('channel_guid', $arr))            ? $arr['channel_guid']            : ''),
        'channel_guid_sig'        => ((array_key_exists('channel_guid_sig', $arr))        ? $arr['channel_guid_sig']        : ''),
        'channel_hash'            => ((array_key_exists('channel_hash', $arr))            ? $arr['channel_hash']            : ''),
        'channel_timezone'        => ((array_key_exists('channel_timezone', $arr))        ? $arr['channel_timezone']        : 'UTC'),
        'channel_location'        => ((array_key_exists('channel_location', $arr))        ? $arr['channel_location']        : ''),
        'channel_theme'           => ((array_key_exists('channel_theme', $arr))           ? $arr['channel_theme']           : ''),
        'channel_startpage'       => ((array_key_exists('channel_startpage', $arr))       ? $arr['channel_startpage']       : ''),
        'channel_pubkey'          => ((array_key_exists('channel_pubkey', $arr))          ? $arr['channel_pubkey']          : ''),
        'channel_prvkey'          => ((array_key_exists('channel_prvkey', $arr))          ? $arr['channel_prvkey']          : ''),
        'channel_notifyflags'     => ((array_key_exists('channel_notifyflags', $arr))     ? $arr['channel_notifyflags']     : '65535'),
        'channel_pageflags'       => ((array_key_exists('channel_pageflags', $arr))       ? $arr['channel_pageflags']       : '0'),
        'channel_dirdate'         => ((array_key_exists('channel_dirdate', $arr))         ? $arr['channel_dirdate']         : NULL_DATE),
        'channel_lastpost'        => ((array_key_exists('channel_lastpost', $arr))        ? $arr['channel_lastpost']        : NULL_DATE),
        'channel_deleted'         => ((array_key_exists('channel_deleted', $arr))         ? $arr['channel_deleted']         : NULL_DATE),
        'channel_active'          => ((array_key_exists('channel_active', $arr))          ? $arr['channel_active']          : NULL_DATE),
        'channel_max_anon_mail'   => ((array_key_exists('channel_max_anon_mail', $arr))   ? $arr['channel_max_anon_mail']   : '10'),
        'channel_max_friend_req'  => ((array_key_exists('channel_max_friend_req', $arr))  ? $arr['channel_max_friend_req']  : '10'),
        'channel_expire_days'     => ((array_key_exists('channel_expire_days', $arr))     ? $arr['channel_expire_days']     : '0'),
        'channel_passwd_reset'    => ((array_key_exists('channel_passwd_reset', $arr))    ? $arr['channel_passwd_reset']    : ''),
        'channel_default_group'   => ((array_key_exists('channel_default_group', $arr))   ? $arr['channel_default_group']   : ''),
        'channel_allow_cid'       => ((array_key_exists('channel_allow_cid', $arr))       ? $arr['channel_allow_cid']       : ''),
        'channel_allow_gid'       => ((array_key_exists('channel_allow_gid', $arr))       ? $arr['channel_allow_gid']       : ''),
        'channel_deny_cid'        => ((array_key_exists('channel_deny_cid', $arr))        ? $arr['channel_deny_cid']        : ''),
        'channel_deny_gid'        => ((array_key_exists('channel_deny_gid', $arr))        ? $arr['channel_deny_gid']        : ''),
        'channel_removed'         => ((array_key_exists('channel_removed', $arr))         ? $arr['channel_removed']         : '0'),
        'channel_system'          => ((array_key_exists('channel_system', $arr))          ? $arr['channel_system']          : '0'),
        'channel_moved'           => ((array_key_exists('channel_moved', $arr))           ? $arr['channel_moved']           : ''),
        'channel_password'        => ((array_key_exists('channel_password', $arr))        ? $arr['channel_password']        : ''),
        'channel_salt'            => ((array_key_exists('channel_salt', $arr))            ? $arr['channel_salt']            : '')
    ];

    return create_table_from_array('channel', $store);
}

function profile_store_lowlevel($arr)
{

    $store = [
        'profile_guid'  => ((array_key_exists('profile_guid', $arr))  ? $arr['profile_guid']  : ''),
        'aid'           => ((array_key_exists('aid', $arr))           ? $arr['aid']           : 0),
        'uid'           => ((array_key_exists('uid', $arr))           ? $arr['uid']           : 0),
        'profile_name'  => ((array_key_exists('profile_name', $arr))  ? $arr['profile_name']  : ''),
        'is_default'    => ((array_key_exists('is_default', $arr))    ? $arr['is_default']    : 0),
        'hide_friends'  => ((array_key_exists('hide_friends', $arr))  ? $arr['hide_friends']  : 0),
        'fullname'      => ((array_key_exists('fullname', $arr))      ? $arr['fullname']      : ''),
        'pdesc'         => ((array_key_exists('pdesc', $arr))         ? $arr['pdesc']         : ''),
        'chandesc'      => ((array_key_exists('chandesc', $arr))      ? $arr['chandesc']      : ''),
        'dob'           => ((array_key_exists('dob', $arr))           ? $arr['dob']           : ''),
        'dob_tz'        => ((array_key_exists('dob_tz', $arr))        ? $arr['dob_tz']        : ''),
        'address'       => ((array_key_exists('address', $arr))       ? $arr['address']       : ''),
        'locality'      => ((array_key_exists('locality', $arr))      ? $arr['locality']      : ''),
        'region'        => ((array_key_exists('region', $arr))        ? $arr['region']        : ''),
        'postal_code'   => ((array_key_exists('postal_code', $arr))   ? $arr['postal_code']   : ''),
        'country_name'  => ((array_key_exists('country_name', $arr))  ? $arr['country_name']  : ''),
        'hometown'      => ((array_key_exists('hometown', $arr))      ? $arr['hometown']      : ''),
        'gender'        => ((array_key_exists('gender', $arr))        ? $arr['gender']        : ''),
        'marital'       => ((array_key_exists('marital', $arr))       ? $arr['marital']       : ''),
        'partner'       => ((array_key_exists('partner', $arr))       ? $arr['partner']       : ''),
        'howlong'       => ((array_key_exists('howlong', $arr))       ? $arr['howlong']       : NULL_DATE),
        'sexual'        => ((array_key_exists('sexual', $arr))        ? $arr['sexual']        : ''),
        'pronouns'      => ((array_key_exists('pronouns', $arr))      ? $arr['pronouns']      : ''),
        'politic'       => ((array_key_exists('politic', $arr))       ? $arr['politic']       : ''),
        'religion'      => ((array_key_exists('religion', $arr))      ? $arr['religion']      : ''),
        'keywords'      => ((array_key_exists('keywords', $arr))      ? $arr['keywords']      : ''),
        'likes'         => ((array_key_exists('likes', $arr))         ? $arr['likes']         : ''),
        'dislikes'      => ((array_key_exists('dislikes', $arr))      ? $arr['dislikes']      : ''),
        'about'         => ((array_key_exists('about', $arr))         ? $arr['about']         : ''),
        'summary'       => ((array_key_exists('summary', $arr))       ? $arr['summary']       : ''),
        'music'         => ((array_key_exists('music', $arr))         ? $arr['music']         : ''),
        'book'          => ((array_key_exists('book', $arr))          ? $arr['book']          : ''),
        'tv'            => ((array_key_exists('tv', $arr))            ? $arr['tv']            : ''),
        'film'          => ((array_key_exists('film', $arr))          ? $arr['film']          : ''),
        'interest'      => ((array_key_exists('interest', $arr))      ? $arr['interest']      : ''),
        'romance'       => ((array_key_exists('romance', $arr))       ? $arr['romance']       : ''),
        'employment'    => ((array_key_exists('employment', $arr))    ? $arr['employment']    : ''),
        'education'     => ((array_key_exists('education', $arr))     ? $arr['education']     : ''),
        'contact'       => ((array_key_exists('contact', $arr))       ? $arr['contact']       : ''),
        'channels'      => ((array_key_exists('channels', $arr))      ? $arr['channels']      : ''),
        'homepage'      => ((array_key_exists('homepage', $arr))      ? $arr['homepage']      : ''),
        'photo'         => ((array_key_exists('photo', $arr))         ? $arr['photo']         : ''),
        'thumb'         => ((array_key_exists('thumb', $arr))         ? $arr['thumb']         : ''),
        'publish'       => ((array_key_exists('publish', $arr))       ? $arr['publish']       : 0),
        'profile_vcard' => ((array_key_exists('profile_vcard', $arr)) ? $arr['profile_vcard'] : '')
    ];

    return create_table_from_array('profile', $store);
}


/**
 * Included here for completeness, but this is a very dangerous operation.
 * It is the caller's responsibility to confirm the requestor's intent and
 * authorisation to do this.
 *
 * @param int $account_id
 * @param bool $local (optional) default true
 * @param bool $unset_session (optional) default true
 * @return bool|array
 */

function account_remove($account_id, $local = true, $unset_session = true)
{

    logger('account_remove: ' . $account_id);

    // Global removal (all clones) not currently supported
    $local = true;

    if (! intval($account_id)) {
        logger('No account.');
        return false;
    }

    // Don't let anybody nuke the only admin account.

    $r = q(
        "select account_id from account where (account_roles & %d) > 0",
        intval(ACCOUNT_ROLE_ADMIN)
    );

    if ($r !== false && count($r) == 1 && $r[0]['account_id'] == $account_id) {
        logger("Unable to remove the only remaining admin account");
        return false;
    }

    $r = q(
        "select * from account where account_id = %d limit 1",
        intval($account_id)
    );

    if (! $r) {
        logger('No account with id: ' . $account_id);
        return false;
    }

    $account_email = $r[0]['account_email'];

    $x = q(
        "select channel_id from channel where channel_account_id = %d",
        intval($account_id)
    );
    if ($x) {
        foreach ($x as $xx) {
            channel_remove($xx['channel_id'], $local, false);
        }
    }

    $r = q(
        "delete from account where account_id = %d",
        intval($account_id)
    );

    if ($unset_session) {
        App::$session->nuke();
        notice(sprintf(t('Account \'%s\' deleted'), $account_email) . EOL);
        goaway(z_root());
    }

    return $r;
}

/**
 * @brief Removes a channel.
 *
 * @param int $channel_id
 * @param bool $local default true
 * @param bool $unset_session default false
 */

function channel_remove($channel_id, $local = true, $unset_session = false)
{

    if (! $channel_id) {
        return;
    }

    // global removal (all clones) not currently supported
    // hence this operation _may_ leave orphan data on remote servers

    $local = true;

    logger('Removing channel: ' . $channel_id);
    logger('local only: ' . intval($local));


    $r = q("select * from channel where channel_id = %d limit 1", intval($channel_id));
    if (! $r) {
        logger('channel not found: ' . $channel_id);
        return;
    }

    $channel = $r[0];

    /**
     * @hooks channel_remove
     *   Called when removing a channel.
     *   * \e array with entry from channel tabel for $channel_id
     */

    call_hooks('channel_remove', $channel);

    $r = q(
        "select iid from iconfig left join item on item.id = iconfig.iid
		where item.uid = %d",
        intval($channel_id)
    );
    if ($r) {
        foreach ($r as $rr) {
            q(
                "delete from iconfig where iid = %d",
                intval($rr['iid'])
            );
        }
    }

    q("DELETE FROM app WHERE app_channel = %d", intval($channel_id));
    q("DELETE FROM atoken WHERE atoken_uid = %d", intval($channel_id));
    q("DELETE FROM chatroom WHERE cr_uid = %d", intval($channel_id));
    q("DELETE FROM conv WHERE uid = %d", intval($channel_id));

    q("DELETE FROM pgrp WHERE uid = %d", intval($channel_id));
    q("DELETE FROM pgrp_member WHERE uid = %d", intval($channel_id));
    q("DELETE FROM event WHERE uid = %d", intval($channel_id));
    q("DELETE FROM menu WHERE menu_channel_id = %d", intval($channel_id));
    q("DELETE FROM menu_item WHERE mitem_channel_id = %d", intval($channel_id));

    q("DELETE FROM notify WHERE uid = %d", intval($channel_id));
    q("DELETE FROM obj WHERE obj_channel = %d", intval($channel_id));


    q("DELETE FROM photo WHERE uid = %d", intval($channel_id));
    q("DELETE FROM attach WHERE uid = %d", intval($channel_id));
    q("DELETE FROM profile WHERE uid = %d", intval($channel_id));
    q("DELETE FROM source WHERE src_channel_id = %d", intval($channel_id));

    $r = q("select hash FROM attach WHERE uid = %d", intval($channel_id));
    if ($r) {
        foreach ($r as $rv) {
            attach_delete($channel_id, $rv['hash']);
        }
    }


    q(
        "delete from abook where abook_xchan = '%s' and abook_self = 1 ",
        dbesc($channel['channel_hash'])
    );

    $r = q(
        "update channel set channel_deleted = '%s', channel_removed = 1 where channel_id = %d",
        dbesc(datetime_convert()),
        intval($channel_id)
    );

    // remove items

    Run::Summon([ 'Channel_purge', $channel_id ]);

    // if this was the default channel, set another one as default

    if (App::$account['account_default_channel'] == $channel_id) {
        $r = q(
            "select channel_id from channel where channel_account_id = %d and channel_removed = 0 limit 1",
            intval(App::$account['account_id']),
            intval(PAGE_REMOVED)
        );
        if ($r) {
            $rr = q(
                "update account set account_default_channel = %d where account_id = %d",
                intval($r[0]['channel_id']),
                intval(App::$account['account_id'])
            );
            logger("Default channel deleted, changing default to channel_id " . $r[0]['channel_id']);
        } else {
            $rr = q(
                "update account set account_default_channel = 0 where account_id = %d",
                intval(App::$account['account_id'])
            );
        }
    }

    logger('deleting hublocs', LOGGER_DEBUG);

    $r = q(
        "update hubloc set hubloc_deleted = 1 where hubloc_hash = '%s' and hubloc_url = '%s' ",
        dbesc($channel['channel_hash']),
        dbesc(z_root())
    );

    // Do we have any valid hublocs remaining?

    $hublocs = 0;

    $r = q(
        "select hubloc_id from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
        dbesc($channel['channel_hash'])
    );
    if ($r) {
        $hublocs = count($r);
    }

    if (! $hublocs) {
        $r = q(
            "update xchan set xchan_deleted = 1 where xchan_hash = '%s' ",
            dbesc($channel['channel_hash'])
        );
        // send a cleanup message to other servers
        Run::Summon([ 'Notifier', 'purge_all', $channel_id ]);
    }

    //remove from file system

    $f = 'store/' . $channel['channel_address'];
    // This shouldn't happen but make sure the address isn't empty because that could do bad things
    if (is_dir($f) && $channel['channel_address']) {
        @rrmdir($f);
    }

    Run::Summon([ 'Directory', $channel_id ]);

    if ($channel_id == local_channel() && $unset_session) {
        App::$session->nuke();
        goaway(z_root());
    }
}

// execute this at least a week after removing a channel

function channel_remove_final($channel_id)
{

    q("delete from abook where abook_channel = %d", intval($channel_id));
    q("delete from abconfig where chan = %d", intval($channel_id));
    q("delete from pconfig where uid = %d", intval($channel_id));
    q("delete from channel where channel_id = %d", intval($channel_id));
}


/**
 * @brief This checks if a channel is allowed to publish executable code.
 *
 * It is up to the caller to determine if the observer or local_channel
 * is in fact the resource owner whose channel_id is being checked.
 *
 * @param int $channel_id
 * @return bool
 */
function channel_codeallowed($channel_id)
{
    if (! intval($channel_id)) {
        return false;
    }

    $x = channelx_by_n($channel_id);
    if (($x) && ($x['channel_pageflags'] & PAGE_ALLOWCODE)) {
        return true;
    }

    return false;
}

function anon_identity_init($reqvars)
{

    $x = [
            'request_vars' => $reqvars,
            'xchan' => null,
            'success' => 'unset'
    ];
    /**
     * @hooks anon_identity_init
     *   * \e array \b request_vars
     *   * \e string \b xchan - return value
     *   * \e string|int \b success - Must be a number, so xchan return value gets used
     */
    call_hooks('anon_identity_init', $x);

    if ($x['success'] !== 'unset' && intval($x['success']) && $x['xchan']) {
        return $x['xchan'];
    }

    // allow a captcha handler to over-ride
    if ($x['success'] !== 'unset' && (intval($x['success']) === 0)) {
        return false;
    }


    $anon_name  = strip_tags(trim($reqvars['anonname']));
    $anon_email = strip_tags(trim($reqvars['anonmail']));
    $anon_url   = strip_tags(trim($reqvars['anonurl']));

    if (! ($anon_name && $anon_email)) {
        logger('anonymous commenter did not complete form');
        return false;
    }

    if (! validate_email($anon_email)) {
        logger('enonymous email not valid');
        return false;
    }

    if (! $anon_url) {
        $anon_url = z_root();
    }

    $hash = hash('md5', $anon_email);

    $x = q(
        "select * from xchan where xchan_guid = '%s' and xchan_hash = '%s' and xchan_network = 'anon' limit 1",
        dbesc($anon_email),
        dbesc($hash)
    );

    if (! $x) {
        xchan_store_lowlevel([
            'xchan_guid'    => $anon_email,
            'xchan_hash'    => $hash,
            'xchan_name'    => $anon_name,
            'xchan_url'     => $anon_url,
            'xchan_network' => 'anon',
            'xchan_updated' => datetime_convert(),
            'xchan_name_date' => datetime_convert()
        ]);


        $x = q(
            "select * from xchan where xchan_guid = '%s' and xchan_hash = '%s' and xchan_network = 'anon' limit 1",
            dbesc($anon_email),
            dbesc($hash)
        );

        $photo = z_root() . '/' . get_default_profile_photo(300);
        $photos = import_remote_xchan_photo($photo, $hash);
        if ($photos) {
            $r = q(
                "update xchan set xchan_updated = '%s', xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_guid = '%s' and xchan_hash = '%s' and xchan_network = 'anon' ",
                dbesc(datetime_convert()),
                dbesc(datetime_convert()),
                dbesc($photos[0]),
                dbesc($photos[1]),
                dbesc($photos[2]),
                dbesc($photos[3]),
                dbesc($anon_email),
                dbesc($hash)
            );
        }
    }

    return $x[0];
}

function channel_url($channel)
{

    // data validation - if this is wrong, log the call stack so we can find the issue

    if (! is_array($channel)) {
        btlogger('not a channel array: ' . print_r($channel, true));
    }

    if ($channel['channel_address'] === App::get_hostname() || intval($channel['channel_system'])) {
        return z_root();
    }

    return (($channel) ? z_root() . '/channel/' . $channel['channel_address'] : z_root());
}

function is_group($uid)
{
    $role = get_pconfig($uid, 'system', 'permissions_role');
    $rolesettings = PermissionRoles::role_perms($role);
    return ((isset($rolesettings['channel_type']) && $rolesettings['channel_type'] === 'group') ? true : false);
}
