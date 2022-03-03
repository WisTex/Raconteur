<?php

use Code\Access\Permissions;
use Code\Access\PermissionLimits;
use Code\Extend\Hook;

require_once('include/security.php');

/**
 * @file include/permissions.php
 *
 * This file contains functions to check and work with permissions.
 *
 */



/**
 * get_all_perms($uid,$observer_xchan)
 *
 * @param int $uid The channel_id associated with the resource owner
 * @param string $observer_xchan The xchan_hash representing the observer
 * @param bool $check_siteblock (default true)
 *          if false, bypass check for "Block Public" on the site
 * @param bool $default_ignored (default true)
 *          if false, lie and pretend the ignored person has permissions you are ignoring (used in channel discovery)
 *
 * @returns array of all permissions, key is permission name, value is true or false
 */
function get_all_perms($uid, $observer_xchan, $check_siteblock = true, $default_ignored = true)
{

    $api = App::get_oauth_key();
    if ($api) {
        return get_all_api_perms($uid, $api);
    }

    $global_perms = Permissions::Perms();

    // Save lots of individual lookups

    $r = null;
    $c = null;
    $x = null;

    $channel_checked = false;
    $onsite_checked  = false;
    $abook_checked   = false;

    $ret = [];

    $abperms = (($uid && $observer_xchan) ? get_abconfig($uid, $observer_xchan, 'system', 'my_perms', '') : '');

    foreach ($global_perms as $perm_name => $permission) {
        // First find out what the channel owner declared permissions to be.

        $channel_perm = intval(PermissionLimits::Get($uid, $perm_name));

        if (! $channel_checked) {
            $r = q(
                "select * from channel where channel_id = %d limit 1",
                intval($uid)
            );
            $channel_checked = true;
        }

        // The uid provided doesn't exist. This would be a big fail.

        if (! $r) {
            $ret[$perm_name] = false;
            continue;
        }

        // Next we're going to check for blocked or ignored contacts.
        // These take priority over all other settings.

        if ($observer_xchan) {
            if ($channel_perm & PERMS_AUTHED) {
                $ret[$perm_name] = true;
                continue;
            }

            if (! $abook_checked) {
                $x = q(
                    "select abook_blocked, abook_ignored, abook_pending, xchan_network from abook 
					left join xchan on abook_xchan = xchan_hash
					where abook_channel = %d and abook_xchan = '%s' and abook_self = 0 limit 1",
                    intval($uid),
                    dbesc($observer_xchan)
                );

                $abook_checked = true;
            }

			if($channel_perm & PERMS_NETWORK) {
				if($x && in_array($x[0]['xchan_network'],['nomad','zot6'])) {
					$ret[$perm_name] = true;
					continue;
				}
			}

            // If they're blocked - they can't read or write

            if (($x) && intval($x[0]['abook_blocked'])) {
                $ret[$perm_name] = false;
                continue;
            }

            // Check if this is a write permission and they are being ignored
            // This flag is only visible internally.

            $blocked_anon_perms = Permissions::BlockedAnonPerms();


            if (($x) && ($default_ignored) && in_array($perm_name, $blocked_anon_perms) && intval($x[0]['abook_ignored'])) {
                $ret[$perm_name] = false;
                continue;
            }
        }

        // system is blocked to anybody who is not authenticated

        if (($check_siteblock) && (! $observer_xchan) && intval(get_config('system', 'block_public'))) {
            $ret[$perm_name] = false;
            continue;
        }

        // Check if this $uid is actually the $observer_xchan - if it's your content
        // you always have permission to do anything
        // if you've moved elsewhere, you will only have read only access

        if (($observer_xchan) && ($r[0]['channel_hash'] === $observer_xchan)) {
            if ($r[0]['channel_moved'] && (in_array($perm_name, $blocked_anon_perms))) {
                $ret[$perm_name] = false;
            } else {
                $ret[$perm_name] = true;
            }
            // moderated is a negative permission, don't moderate your own posts
            if ($perm_name === 'moderated') {
                $ret[$perm_name] = false;
            }

            continue;
        }

        // Anybody at all (that wasn't blocked or ignored). They have permission.

        if ($channel_perm & PERMS_PUBLIC) {
            $ret[$perm_name] = true;
            continue;
        }

        // From here on out, we need to know who they are. If we can't figure it
        // out, permission is denied.

        if (! $observer_xchan) {
            $ret[$perm_name] = false;
            continue;
        }

        // If we're still here, we have an observer, check the network.

        if ($channel_perm & PERMS_NETWORK) {
            if ($x && $x[0]['xchan_network'] === 'zot6') {
                $ret[$perm_name] = true;
                continue;
            }
        }

        // If PERMS_SITE is specified, find out if they've got an account on this hub

        if ($channel_perm & PERMS_SITE) {
            if (! $onsite_checked) {
                $c = q(
                    "select channel_hash from channel where channel_hash = '%s' limit 1",
                    dbesc($observer_xchan)
                );

                $onsite_checked = true;
            }

            if ($c) {
                $ret[$perm_name] = true;
            } else {
                $ret[$perm_name] = false;
            }

            continue;
        }

        // From here on we require that the observer be a connection and
        // handle whether we're allowing any, approved or specific ones

        if (! $x) {
            $ret[$perm_name] = false;
            continue;
        }

        // They are in your address book, but haven't been approved

        if ($channel_perm & PERMS_PENDING) {
            $ret[$perm_name] = true;
            continue;
        }

        if (intval($x[0]['abook_pending'])) {
            $ret[$perm_name] = false;
            continue;
        }

        // They're a contact, so they have permission

        if ($channel_perm & PERMS_CONTACTS) {
            $ret[$perm_name] = true;
            continue;
        }

        // Permission granted to certain channels. Let's see if the observer is one of them

        if ($channel_perm & PERMS_SPECIFIC) {
            if ($abperms) {
                $arr = explode(',', $abperms);
                if ($arr) {
                    if (in_array($perm_name, $arr)) {
                        $ret[$perm_name] = true;
                    } else {
                        $ret[$perm_name] = false;
                    }
                }
                continue;
            }
        }

        // No permissions allowed.

        $ret[$perm_name] = false;
        continue;
    }

    $arr = array(
        'channel_id'    => $uid,
        'observer_hash' => $observer_xchan,
        'permissions'   => $ret);

    Hook::call('get_all_perms', $arr);

    return $arr['permissions'];
}

/**
 * @brief Checks if given permission is allowed for given observer on a channel.
 *
 * Checks if the given observer with the hash $observer_xchan has permission
 * $permission on channel_id $uid.
 *
 * @param int $uid The channel_id associated with the resource owner
 * @param string $observer_xchan The xchan_hash representing the observer
 * @param string $permission
 * @param bool $check_siteblock (default true)
 *     if false bypass check for "Block Public" at the site level
 * @return bool true if permission is allowed for observer on channel
 */
function perm_is_allowed($uid, $observer_xchan, $permission, $check_siteblock = true)
{

    $api = App::get_oauth_key();
    if ($api) {
        return api_perm_is_allowed($uid, $api, $permission);
    }

    $arr = [
        'channel_id'    => $uid,
        'observer_hash' => $observer_xchan,
        'permission'    => $permission,
        'result'        => 'unset'
    ];

    Hook::call('perm_is_allowed', $arr);
    if ($arr['result'] !== 'unset') {
        return $arr['result'];
    }

    $global_perms = Permissions::Perms();

    // First find out what the channel owner declared permissions to be.

    $channel_perm = PermissionLimits::Get($uid, $permission);
    if ($channel_perm === false) {
        return false;
    }

    $r = q(
        "select channel_pageflags, channel_moved, channel_hash from channel where channel_id = %d limit 1",
        intval($uid)
    );
    if (! $r) {
        return false;
    }

    $blocked_anon_perms = Permissions::BlockedAnonPerms();

    if ($observer_xchan) {
        if ($channel_perm & PERMS_AUTHED) {
            return true;
        }

        $x = q(
            "select abook_blocked, abook_ignored, abook_pending, xchan_network from abook left join xchan on abook_xchan = xchan_hash 
			where abook_channel = %d and abook_xchan = '%s' and abook_self = 0 limit 1",
            intval($uid),
            dbesc($observer_xchan)
        );

        // If they're blocked - they can't read or write

        if (($x) && intval($x[0]['abook_blocked'])) {
            return false;
        }

        if (($x) && in_array($permission, $blocked_anon_perms) && intval($x[0]['abook_ignored'])) {
            return false;
        }

        $abperms = get_abconfig($uid, $observer_xchan, 'system', 'my_perms', '');
    }


    // system is blocked to anybody who is not authenticated

    if (($check_siteblock) && (! $observer_xchan) && intval(get_config('system', 'block_public'))) {
        return false;
    }

    // Check if this $uid is actually the $observer_xchan
    // you will have full access unless the channel was moved -
    // in which case you will have read_only access

    if ($r[0]['channel_hash'] === $observer_xchan) {
        // moderated is a negative permission
        if ($permission === 'moderated') {
            return false;
        }
        if ($r[0]['channel_moved'] && (in_array($permission, $blocked_anon_perms))) {
            return false;
        } else {
            return true;
        }
    }

    if ($channel_perm & PERMS_PUBLIC) {
        return true;
    }

    // If it's an unauthenticated observer, we only need to see if PERMS_PUBLIC is set

    if (! $observer_xchan) {
        return false;
    }

    // If we're still here, we have an observer, check the network.

	if ($channel_perm & PERMS_NETWORK) {
		if ($x && in_array($x[0]['xchan_network'],['nomad','zot6'])) {
			return true;
		}
	}

    // If PERMS_SITE is specified, find out if they've got an account on this hub

    if ($channel_perm & PERMS_SITE) {
        $c = q(
            "select channel_hash from channel where channel_hash = '%s' limit 1",
            dbesc($observer_xchan)
        );
        if ($c) {
            return true;
        }
        return false;
    }

    // From here on we require that the observer be a connection and
    // handle whether we're allowing any, approved or specific ones

    if (! $x) {
        return false;
    }

    // They are in your address book, but haven't been approved

    if ($channel_perm & PERMS_PENDING) {
        return true;
    }

    if (intval($x[0]['abook_pending'])) {
        return false;
    }

    // They're a contact, so they have permission

    if ($channel_perm & PERMS_CONTACTS) {
        return true;
    }

    // Permission granted to certain channels. Let's see if the observer is one of them

    if (($r) && ($channel_perm & PERMS_SPECIFIC)) {
        if ($abperms) {
            $arr = explode(',', $abperms);
            if ($arr) {
                if (in_array($permission, $arr)) {
                    return true;
                }
            }
        }
        return false;
    }

    // No permissions allowed.

    return false;
}

function get_all_api_perms($uid, $api)
{

    $global_perms = Permissions::Perms();

    $ret = [];

    $r = q(
        "select * from xperm where xp_client = '%s' and xp_channel = %d",
        dbesc($api),
        intval($uid)
    );

    if (! $r) {
        return false;
    }

    $allow_all = false;
    $allowed = [];
    foreach ($r as $rr) {
        if ($rr['xp_perm'] === 'all') {
            $allow_all = true;
        }
        if (! in_array($rr['xp_perm'], $allowed)) {
            $allowed[] = $rr['xp_perm'];
        }
    }

    foreach ($global_perms as $perm_name => $permission) {
        if ($allow_all || in_array($perm_name, $allowed)) {
            $ret[$perm_name] = true;
        } else {
            $ret[$perm_name] = false;
        }
    }

    $arr = array(
        'channel_id'    => $uid,
        'observer_hash' => $observer_xchan,
        'permissions'   => $ret);

    Hook::call('get_all_api_perms', $arr);

    return $arr['permissions'];
}


function api_perm_is_allowed($uid, $api, $permission)
{

    $arr = array(
        'channel_id'    => $uid,
        'observer_hash' => $observer_xchan,
        'permission'    => $permission,
        'result'        => false
    );

    Hook::call('api_perm_is_allowed', $arr);
    if ($arr['result']) {
        return true;
    }

    $r = q(
        "select * from xperm where xp_client = '%s' and xp_channel = %d and ( xp_perm = 'all' OR xp_perm = '%s' )",
        dbesc($api),
        intval($uid),
        dbesc($permission)
    );

    if (! $r) {
        return false;
    }

    foreach ($r as $rr) {
        if ($rr['xp_perm'] === 'all' || $rr['xp_perm'] === $permission) {
            return true;
        }
    }

    return false;
}



// Check a simple array of observers against a permissions
// return a simple array of those with permission

function check_list_permissions($uid, $arr, $perm)
{
    $result = [];
    if ($arr) {
        foreach ($arr as $x) {
            if (perm_is_allowed($uid, $x, $perm)) {
                $result[] = $x;
            }
        }
    }

    return($result);
}

/**
 * @brief Sets site wide default permissions.
 *
 * @return array
 */
function site_default_perms()
{

    $ret = [];

    $typical = array(
        'view_stream'   => PERMS_PUBLIC,
        'view_profile'  => PERMS_PUBLIC,
        'view_contacts' => PERMS_PUBLIC,
        'view_storage'  => PERMS_PUBLIC,
        'view_pages'    => PERMS_PUBLIC,
        'view_wiki'     => PERMS_PUBLIC,
        'send_stream'   => PERMS_SPECIFIC,
        'post_wall'     => PERMS_SPECIFIC,
        'post_comments' => PERMS_SPECIFIC,
        'post_mail'     => PERMS_SPECIFIC,
        'tag_deliver'   => PERMS_SPECIFIC,
        'chat'          => PERMS_SPECIFIC,
        'write_storage' => PERMS_SPECIFIC,
        'write_pages'   => PERMS_SPECIFIC,
        'write_wiki'    => PERMS_SPECIFIC,
        'delegate'      => PERMS_SPECIFIC,
        'post_like'     => PERMS_NETWORK
    );

    $global_perms = Permissions::Perms();

    foreach ($global_perms as $perm => $v) {
        $x = get_config('default_perms', $perm, $typical[$perm]);
        $ret[$perm] = $x;
    }

    return $ret;
}


function their_perms_contains($channel_id, $xchan_hash, $perm)
{
    $x = get_abconfig($channel_id, $xchan_hash, 'system', 'their_perms');
    if ($x) {
        $y = explode(',', $x);
        if (in_array($perm, $y)) {
            return true;
        }
    }
    return false;
}

function my_perms_contains($channel_id, $xchan_hash, $perm)
{
    $x = get_abconfig($channel_id, $xchan_hash, 'system', 'my_perms');
    if ($x) {
        $y = explode(',', $x);
        if (in_array($perm, $y)) {
            return true;
        }
    }
    return false;
}
