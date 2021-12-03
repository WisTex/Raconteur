<?php

/** @file */

use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;
use Zotlabs\Daemon\Run;
use Zotlabs\Lib\Libsync;

function abook_store_lowlevel($arr)
{

    $store = [
        'abook_account'     => ((array_key_exists('abook_account', $arr))     ? $arr['abook_account']     : 0),
        'abook_channel'     => ((array_key_exists('abook_channel', $arr))     ? $arr['abook_channel']     : 0),
        'abook_xchan'       => ((array_key_exists('abook_xchan', $arr))       ? $arr['abook_xchan']       : ''),
        'abook_alias'       => ((array_key_exists('abook_alias', $arr))       ? $arr['abook_alias']       : ''),
        'abook_my_perms'    => ((array_key_exists('abook_my_perms', $arr))    ? $arr['abook_my_perms']    : 0),
        'abook_their_perms' => ((array_key_exists('abook_their_perms', $arr)) ? $arr['abook_their_perms'] : 0),
        'abook_closeness'   => ((array_key_exists('abook_closeness', $arr))   ? $arr['abook_closeness']   : 99),
        'abook_created'     => ((array_key_exists('abook_created', $arr))     ? $arr['abook_created']     : NULL_DATE),
        'abook_updated'     => ((array_key_exists('abook_updated', $arr))     ? $arr['abook_updated']     : NULL_DATE),
        'abook_connected'   => ((array_key_exists('abook_connected', $arr))   ? $arr['abook_connected']   : NULL_DATE),
        'abook_dob'         => ((array_key_exists('abook_dob', $arr))         ? $arr['abook_dob']         : NULL_DATE),
        'abook_flags'       => ((array_key_exists('abook_flags', $arr))       ? $arr['abook_flags']       : 0),
        'abook_censor'      => ((array_key_exists('abook_censor', $arr))      ? $arr['abook_censor']      : 0),
        'abook_blocked'     => ((array_key_exists('abook_blocked', $arr))     ? $arr['abook_blocked']     : 0),
        'abook_ignored'     => ((array_key_exists('abook_ignored', $arr))     ? $arr['abook_ignored']     : 0),
        'abook_hidden'      => ((array_key_exists('abook_hidden', $arr))      ? $arr['abook_hidden']      : 0),
        'abook_archived'    => ((array_key_exists('abook_archived', $arr))    ? $arr['abook_archived']    : 0),
        'abook_pending'     => ((array_key_exists('abook_pending', $arr))     ? $arr['abook_pending']     : 0),
        'abook_unconnected' => ((array_key_exists('abook_unconnected', $arr)) ? $arr['abook_unconnected'] : 0),
        'abook_self'        => ((array_key_exists('abook_self', $arr))        ? $arr['abook_self']        : 0),
        'abook_feed'        => ((array_key_exists('abook_feed', $arr))        ? $arr['abook_feed']        : 0),
        'abook_not_here'    => ((array_key_exists('abook_not_here', $arr))    ? $arr['abook_not_here']    : 0),
        'abook_profile'     => ((array_key_exists('abook_profile', $arr))     ? $arr['abook_profile']     : ''),
        'abook_incl'        => ((array_key_exists('abook_incl', $arr))        ? $arr['abook_incl']        : ''),
        'abook_excl'        => ((array_key_exists('abook_excl', $arr))        ? $arr['abook_excl']        : ''),
        'abook_instance'    => ((array_key_exists('abook_instance', $arr))    ? $arr['abook_instance']    : '')
    ];

    return create_table_from_array('abook', $store);
}


function rconnect_url($channel_id, $xchan)
{

    if (! $xchan) {
        return z_root() . '/fedi_id/' . $channel_id;
    }

    $r = q(
        "select abook_id from abook where abook_channel = %d and abook_xchan = '%s' limit 1",
        intval($channel_id),
        dbesc($xchan)
    );

    // Already connected
    if ($r) {
        return EMPTY_STR;
    }

    $r = q(
        "select * from xchan where xchan_hash = '%s' limit 1",
        dbesc($xchan)
    );

    if (($r) && ($r[0]['xchan_follow'])) {
        return $r[0]['xchan_follow'];
    }

    $r = q(
        "select hubloc_url from hubloc where hubloc_hash = '%s' and hubloc_primary = 1 limit 1",
        dbesc($xchan)
    );

    if ($r) {
        return $r[0]['hubloc_url'] . '/follow?f=&url=%s';
    }

    return EMPTY_STR;
}

function abook_connections($channel_id, $sql_conditions = '')
{
    $r = q(
        "select * from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d
		and abook_self = 0 $sql_conditions",
        intval($channel_id)
    );
    return(($r) ? $r : []);
}


function abook_by_hash($channel_id, $hash)
{
    $r = q(
        "select * from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d
		and abook_self = 0 and abook_xchan = '%s'",
        intval($channel_id),
        dbesc($hash)
    );
    return(($r) ? array_shift($r) : false);
}

function abook_self($channel_id)
{
    $r = q(
        "select * from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d
		and abook_self = 1 limit 1",
        intval($channel_id)
    );
    return(($r) ? $r[0] : []);
}


function vcard_from_xchan($xchan, $observer = null, $mode = '')
{

    if (! $xchan) {
        if (App::$poi) {
            $xchan = App::$poi;
        } elseif (is_array(App::$profile) && App::$profile['channel_hash']) {
            $r = q(
                "select * from xchan where xchan_hash = '%s' limit 1",
                dbesc(App::$profile['channel_hash'])
            );
            if ($r) {
                $xchan = $r[0];
            }
        }
    }

    if (! $xchan) {
        return;
    }

    $connect = false;
    if (local_channel()) {
        $r = q(
            "select * from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
            dbesc($xchan['xchan_hash']),
            intval(local_channel())
        );
        if (! $r) {
            $connect = t('Connect');
        }
    }

    // don't provide a connect button for transient or one-way identities

    if (in_array($xchan['xchan_network'], [ 'rss','anon','token','unknown' ])) {
        $connect = false;
    }

    if (array_key_exists('channel_id', $xchan)) {
        App::$profile_uid = $xchan['channel_id'];
    }

    $url = (($observer)
        ? z_root() . '/magic?f=&owa=1&bdest=' . bin2hex($xchan['xchan_url']) . '&addr=' . $xchan['xchan_addr']
        : $xchan['xchan_url']
    );

    return replace_macros(get_markup_template('xchan_vcard.tpl'), [
        '$name'    => $xchan['xchan_name'],
        '$photo'   => ((is_array(App::$profile) && array_key_exists('photo', App::$profile)) ? App::$profile['photo'] : $xchan['xchan_photo_l']),
        '$follow'  => urlencode(($xchan['xchan_addr']) ? $xchan['xchan_addr'] : $xchan['xchan_url']),
        '$link'    => zid($xchan['xchan_url']),
        '$connect' => $connect,
        '$newwin'  => (($mode === 'chanview') ? t('New window') : EMPTY_STR),
        '$newtit'  => t('Open the selected location in a different window or browser tab'),
        '$url'     => $url,
    ]);
}

function abook_toggle_flag($abook, $flag)
{

    $field = EMPTY_STR;

    switch ($flag) {
        case ABOOK_FLAG_BLOCKED:
            $field = 'abook_blocked';
            break;
        case ABOOK_FLAG_IGNORED:
            $field = 'abook_ignored';
            break;
        case ABOOK_FLAG_CENSORED:
            $field = 'abook_censor';
            break;
        case ABOOK_FLAG_HIDDEN:
            $field = 'abook_hidden';
            break;
        case ABOOK_FLAG_ARCHIVED:
            $field = 'abook_archived';
            break;
        case ABOOK_FLAG_PENDING:
            $field = 'abook_pending';
            break;
        case ABOOK_FLAG_UNCONNECTED:
            $field = 'abook_unconnected';
            break;
        case ABOOK_FLAG_SELF:
            $field = 'abook_self';
            break;
        case ABOOK_FLAG_FEED:
            $field = 'abook_feed';
            break;
        default:
            break;
    }
    if (! $field) {
        return;
    }

    $r = q(
        "UPDATE abook set $field = (1 - $field) where abook_id = %d and abook_channel = %d",
        intval($abook['abook_id']),
        intval($abook['abook_channel'])
    );

    // if unsetting the archive bit, update the timestamps so we'll try to connect for an additional 30 days.

    if (($flag === ABOOK_FLAG_ARCHIVED) && (intval($abook['abook_archived']))) {
        $r = q(
            "update abook set abook_connected = '%s', abook_updated = '%s' 
			where abook_id = %d and abook_channel = %d",
            dbesc(datetime_convert()),
            dbesc(datetime_convert()),
            intval($abook['abook_id']),
            intval($abook['abook_channel'])
        );
    }

    return $r;
}



/**
 * mark any hubs "offline" that haven't been heard from in more than 30 days
 * Allow them to redeem themselves if they come back later.
 * Then go through all those that are newly marked and see if any other hubs
 * are attached to the controlling xchan that are still alive.
 * If not, they're dead (although they could come back some day).
 */


function mark_orphan_hubsxchans()
{

    return;

    $dirmode = intval(get_config('system', 'directory_mode'));
    if ($dirmode == DIRECTORY_MODE_NORMAL) {
        return;
    }

    $r = q(
        "update hubloc set hubloc_deleted = 1 where hubloc_deleted = 0 
		and hubloc_network = 'zot6' and hubloc_connected < %s - interval %s",
        db_utcnow(),
        db_quoteinterval('36 day')
    );

    $r = q("select hubloc_id, hubloc_hash from hubloc where hubloc_deleted = 1 and hubloc_orphancheck = 0");

    if ($r) {
        foreach ($r as $rr) {
            // see if any other hublocs are still alive for this channel

            $x = q(
                "select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
                dbesc($rr['hubloc_hash'])
            );
            if ($x) {
                // yes - if the xchan was marked as an orphan, undo it

                $y = q(
                    "update xchan set xchan_orphan = 0 where xchan_orphan = 1 and xchan_hash = '%s'",
                    dbesc($rr['hubloc_hash'])
                );
            } else {
                // nope - mark the xchan as an orphan

                $y = q(
                    "update xchan set xchan_orphan = 1 where xchan_hash = '%s'",
                    dbesc($rr['hubloc_hash'])
                );
            }

            // mark that we've checked this entry so we don't need to do it again

            $y = q(
                "update hubloc set hubloc_orphancheck = 1 where hubloc_id = %d",
                dbesc($rr['hubloc_id'])
            );
        }
    }
}




function remove_all_xchan_resources($xchan, $channel_id = 0)
{

    // $channel_id is reserved for future use.


    if (intval($channel_id) === 0) {
        if (! $xchan) {
            return;
        }

        // this function is only to be executed on remote servers where only the xchan exists and there is no associated channel.

        $c = q(
            "select channel_id from channel where channel_hash = '%s'",
            dbesc($xchan)
        );

        if ($c) {
            return;
        }

        $dirmode = intval(get_config('system', 'directory_mode'));

        // note: we will not remove "guest" submitted files/photos this xchan created in the file spaces of others.
        // We will however remove all their posts and comments.

        $r = q(
            "select id from item where ( author_xchan = '%s' or owner_xchan = '%s' ) ",
            dbesc($xchan),
            dbesc($xchan)
        );
        if ($r) {
            foreach ($r as $rr) {
                drop_item($rr['id'], false);
            }
        }
        $r = q(
            "delete from event where event_xchan = '%s'",
            dbesc($xchan)
        );
        $r = q(
            "delete from pgrp_member where xchan = '%s'",
            dbesc($xchan)
        );

        $r = q(
            "delete from xlink where ( xlink_xchan = '%s' or xlink_link = '%s' )",
            dbesc($xchan),
            dbesc($xchan)
        );

        $r = q(
            "delete from abook where abook_xchan = '%s'",
            dbesc($xchan)
        );

        $r = q(
            "delete from abconfig where xchan = '%s'",
            dbesc($xchan)
        );

        if ($dirmode === false || $dirmode == DIRECTORY_MODE_NORMAL) {
            $r = q(
                "delete from xchan where xchan_hash = '%s'",
                dbesc($xchan)
            );
            $r = q(
                "delete from hubloc where hubloc_hash = '%s'",
                dbesc($xchan)
            );
            $r = q(
                "delete from xprof where xprof_hash = '%s'",
                dbesc($xchan)
            );
        } else {
            // directory servers need to keep the record around for sync purposes - mark it deleted

            $r = q(
                "update hubloc set hubloc_deleted = 1 where hubloc_hash = '%s'",
                dbesc($xchan)
            );

            $r = q(
                "update xchan set xchan_deleted = 1 where xchan_hash = '%s'",
                dbesc($xchan)
            );
        }
    }
}





function contact_remove($channel_id, $abook_id, $atoken_sync = false)
{

    if ((! $channel_id) || (! $abook_id)) {
        return false;
    }

    logger('removing connection ' . $abook_id . ' for channel ' . $channel_id, LOGGER_DEBUG);


    $x = [
        'channel_id' => $channel_id,
        'abook_id'   => $abook_id
    ];

    call_hooks('connection_remove', $x);

    $archive = get_pconfig($channel_id, 'system', 'archive_removed_contacts');
    if ($archive) {
        q(
            "update abook set abook_archived = 1 where abook_id = %d and abook_channel = %d",
            intval($abook_id),
            intval($channel_id)
        );
        return true;
    }

    $r = q(
        "select * from abook where abook_id = %d and abook_channel = %d limit 1",
        intval($abook_id),
        intval($channel_id)
    );

    if (! $r) {
        return false;
    }

    $abook = $r[0];

    if (intval($abook['abook_self'])) {
        return false;
    }

    // if this is an atoken, delete the atoken record

    if ($atoken_sync) {
        $xchan = q(
            "select * from xchan where xchan_hash = '%s'",
            dbesc($abook['abook_xchan'])
        );
        if ($xchan && strpos($xchan[0]['xchan_addr'], 'guest:') === 0 && strpos($abook['abook_xchan'], '.')) {
            $atoken_guid = substr($abook['abook_xchan'], strrpos($abook['abook_xchan'], '.') + 1);
            if ($atoken_guid) {
                atoken_delete_and_sync($channel_id, $atoken_guid);
            }
        }
    }

    // remove items in the background as this can take some time

    Run::Summon([ 'Delxitems', $channel_id, $abook['abook_xchan'] ]);

    $r = q(
        "delete from abook where abook_id = %d and abook_channel = %d",
        intval($abook['abook_id']),
        intval($channel_id)
    );

    $r = q(
        "delete from event where event_xchan = '%s' and uid = %d",
        dbesc($abook['abook_xchan']),
        intval($channel_id)
    );

    $r = q(
        "delete from pgrp_member where xchan = '%s' and uid = %d",
        dbesc($abook['abook_xchan']),
        intval($channel_id)
    );

    $r = q(
        "delete from mail where ( from_xchan = '%s' or to_xchan = '%s' ) and channel_id = %d ",
        dbesc($abook['abook_xchan']),
        dbesc($abook['abook_xchan']),
        intval($channel_id)
    );

    $r = q(
        "delete from abconfig where chan = %d and xchan = '%s'",
        intval($channel_id),
        dbesc($abook['abook_xchan'])
    );

    return true;
}

function remove_abook_items($channel_id, $xchan_hash)
{

    $r = q(
        "select id, parent from item where (owner_xchan = '%s' or author_xchan = '%s') and uid = %d and item_retained = 0 and item_starred = 0",
        dbesc($xchan_hash),
        dbesc($xchan_hash),
        intval($channel_id)
    );
    if (! $r) {
        return;
    }

    $already_saved = [];
    foreach ($r as $rr) {
        $w = $x = $y = null;

        // optimise so we only process newly seen parent items
        if (in_array($rr['parent'], $already_saved)) {
            continue;
        }
        // if this isn't the parent, fetch the parent's item_retained  and item_starred to see if the conversation
        // should be retained
        if ($rr['id'] != $rr['parent']) {
            $w = q(
                "select id, item_retained, item_starred from item where id = %d",
                intval($rr['parent'])
            );
            if ($w) {
                // see if the conversation was filed
                $x = q(
                    "select uid from term where otype = %d and oid = %d and ttype = %d limit 1",
                    intval(TERM_OBJ_POST),
                    intval($w[0]['id']),
                    intval(TERM_FILE)
                );
                if (intval($w[0]['item_retained']) || intval($w[0]['item_starred']) || $x) {
                    $already_saved[] = $rr['parent'];
                    continue;
                }
            }
        }
        // see if this item was filed
        $y = q(
            "select uid from term where otype = %d and oid = %d and ttype = %d limit 1",
            intval(TERM_OBJ_POST),
            intval($rr['id']),
            intval(TERM_FILE)
        );
        if ($y) {
            continue;
        }
        drop_item($rr['id'], false);
    }
}


function random_profile()
{
    $randfunc = db_getfunc('rand');

    $checkrandom = get_config('randprofile', 'check'); // False by default
    $retryrandom = intval(get_config('randprofile', 'retry'));
    if ($retryrandom == 0) {
        $retryrandom = 5;
    }

    for ($i = 0; $i < $retryrandom; $i++) {
        $r = q(
            "select xchan_url, xchan_hash from xchan left join hubloc on hubloc_hash = xchan_hash where
			xchan_hidden = 0 and xchan_network = 'zot6' and xchan_deleted = 0
			and hubloc_connected > %s - interval %s order by $randfunc limit 1",
            db_utcnow(),
            db_quoteinterval('30 day')
        );

        if (!$r) {
            return EMPTY_STR; // Couldn't get a random channel
        }
        if ($checkrandom) {
            $x = z_fetch_url($r[0]['xchan_url']);
            if ($x['success']) {
                return $r[0]['xchan_hash'];
            } else {
                logger('Random channel turned out to be bad.');
            }
        } else {
            return $r[0]['xchan_hash'];
        }
    }
    return EMPTY_STR;
}

function update_vcard($arr, $vcard = null)
{


    //  logger('update_vcard: ' . print_r($arr,true));

    $fn = $arr['fn'];


    // This isn't strictly correct and could be a cause for concern.
    // 'N' => array_reverse(explode(' ', $fn))


    // What we really want is
    // 'N' => Adams;John;Quincy;Reverend,Dr.;III
    // which is a very difficult parsing problem especially if you allow
    // the surname to contain spaces. The only way to be sure to get it
    // right is to provide a form to input all the various fields and not
    // try to extract it from the FN.

    if (! $vcard) {
        $vcard = new VCard([
            'FN' => $fn,
            'N' => array_reverse(explode(' ', $fn))
        ]);
    } else {
        $vcard->FN = $fn;
        $vcard->N = array_reverse(explode(' ', $fn));
    }

    $org = $arr['org'];
    if ($org) {
        $vcard->ORG = $org;
    }

    $title = $arr['title'];
    if ($title) {
        $vcard->TITLE = $title;
    }

    $tel = $arr['tel'];
    $tel_type = $arr['tel_type'];
    if ($tel) {
        $i = 0;
        foreach ($tel as $item) {
            if ($item) {
                $vcard->add('TEL', $item, ['type' => $tel_type[$i]]);
            }
            $i++;
        }
    }

    $email = $arr['email'];
    $email_type = $arr['email_type'];
    if ($email) {
        $i = 0;
        foreach ($email as $item) {
            if ($item) {
                $vcard->add('EMAIL', $item, ['type' => $email_type[$i]]);
            }
            $i++;
        }
    }

    $impp = $arr['impp'];
    $impp_type = $arr['impp_type'];
    if ($impp) {
        $i = 0;
        foreach ($impp as $item) {
            if ($item) {
                $vcard->add('IMPP', $item, ['type' => $impp_type[$i]]);
            }
            $i++;
        }
    }

    $url = $arr['url'];
    $url_type = $arr['url_type'];
    if ($url) {
        $i = 0;
        foreach ($url as $item) {
            if ($item) {
                $vcard->add('URL', $item, ['type' => $url_type[$i]]);
            }
            $i++;
        }
    }

    $adr = $arr['adr'];
    $adr_type = $arr['adr_type'];

    if ($adr) {
        $i = 0;
        foreach ($adr as $item) {
            if ($item) {
                $vcard->add('ADR', $item, ['type' => $adr_type[$i]]);
            }
            $i++;
        }
    }

    $note = $arr['note'];
    if ($note) {
        $vcard->NOTE = $note;
    }

    return $vcard->serialize();
}

function get_vcard_array($vc, $id)
{

    $photo = '';
    if ($vc->PHOTO) {
        $photo_value = strtolower($vc->PHOTO->getValueType()); // binary or uri
        if ($photo_value === 'binary') {
            $photo_type = strtolower($vc->PHOTO['TYPE']); // mime jpeg, png or gif
            $photo = 'data:image/' . $photo_type . ';base64,' . base64_encode((string)$vc->PHOTO);
        } else {
            $url = parse_url((string)$vc->PHOTO);
            $photo = 'data:' . $url['path'];
        }
    }

    $fn = '';
    if ($vc->FN) {
        $fn = (string) escape_tags($vc->FN);
    }

    $org = '';
    if ($vc->ORG) {
        $org = (string) escape_tags($vc->ORG);
    }

    $title = '';
    if ($vc->TITLE) {
        $title = (string) escape_tags($vc->TITLE);
    }

    $tels = [];
    if ($vc->TEL) {
        foreach ($vc->TEL as $tel) {
            $type = (($tel['TYPE']) ? vcard_translate_type((string)$tel['TYPE']) : '');
            $tels[] = [
                'type' => $type,
                'nr' => (string) escape_tags($tel)
            ];
        }
    }
    $emails = [];
    if ($vc->EMAIL) {
        foreach ($vc->EMAIL as $email) {
            $type = (($email['TYPE']) ? vcard_translate_type((string)$email['TYPE']) : '');
            $emails[] = [
                'type' => $type,
                'address' => (string) escape_tags($email)
            ];
        }
    }

    $impps = [];
    if ($vc->IMPP) {
        foreach ($vc->IMPP as $impp) {
            $type = (($impp['TYPE']) ? vcard_translate_type((string)$impp['TYPE']) : '');
            $impps[] = [
                'type' => $type,
                'address' => (string) escape_tags($impp)
            ];
        }
    }

    $urls = [];
    if ($vc->URL) {
        foreach ($vc->URL as $url) {
            $type = (($url['TYPE']) ? vcard_translate_type((string)$url['TYPE']) : '');
            $urls[] = [
                'type' => $type,
                'address' => (string) escape_tags($url)
            ];
        }
    }

    $adrs = [];
    if ($vc->ADR) {
        foreach ($vc->ADR as $adr) {
            $type = (($adr['TYPE']) ? vcard_translate_type((string)$adr['TYPE']) : '');
            $entry = [
                'type' => $type,
                'address' => $adr->getParts()
            ];

            if (is_array($entry['address'])) {
                array_walk($entry['address'], 'array_escape_tags');
            } else {
                $entry['address'] = (string) escape_tags($entry['address']);
            }

            $adrs[] = $entry;
        }
    }

    $note = '';
    if ($vc->NOTE) {
        $note = (string) escape_tags($vc->NOTE);
    }

    $card = [
        'id'     => $id,
        'photo'  => $photo,
        'fn'     => $fn,
        'org'    => $org,
        'title'  => $title,
        'tels'   => $tels,
        'emails' => $emails,
        'impps'  => $impps,
        'urls'   => $urls,
        'adrs'   => $adrs,
        'note'   => $note
    ];

    return $card;
}


function vcard_translate_type($type)
{

    if (!$type) {
        return;
    }

    $type = strtoupper($type);

    $map = [
        'CELL' => t('Mobile'),
        'HOME' => t('Home'),
        'HOME,VOICE' => t('Home, Voice'),
        'HOME,FAX' => t('Home, Fax'),
        'WORK' => t('Work'),
        'WORK,VOICE' => t('Work, Voice'),
        'WORK,FAX' => t('Work, Fax'),
        'OTHER' => t('Other')
    ];

    if (array_key_exists($type, $map)) {
        return [$type, $map[$type]];
    } else {
        return [$type, t('Other') . ' (' . $type . ')'];
    }
}


function vcard_query(&$r)
{

    $arr = [];

    if ($r && is_array($r) && count($r)) {
        $uid = $r[0]['abook_channel'];
        foreach ($r as $rv) {
            if ($rv['abook_xchan'] && (! in_array("'" . dbesc($rv['abook_xchan']) . "'", $arr))) {
                $arr[] = "'" . dbesc($rv['abook_xchan']) . "'";
            }
        }
    }

    if ($arr) {
        $a = q(
            "select * from abconfig where chan = %d and xchan in (" . protect_sprintf(implode(',', $arr)) . ") and cat = 'system' and k = 'vcard'",
            intval($uid)
        );
        if ($a) {
            foreach ($a as $av) {
                for ($x = 0; $x < count($r); $x++) {
                    if ($r[$x]['abook_xchan'] == $av['xchan']) {
                        $vctmp = Reader::read($av['v']);
                        $r[$x]['vcard'] = (($vctmp) ? get_vcard_array($vctmp, $r[$x]['abook_id']) : [] );
                    }
                }
            }
        }
    }
}


function contact_profile_assign($current)
{

    $o = '';

    $o .= "<select id=\"contact-profile-selector\" name=\"profile_assign\" class=\"form-control\"/>\r\n";

    $r = q(
        "SELECT profile_guid, profile_name FROM profile WHERE uid = %d",
        intval($_SESSION['uid'])
    );

    if ($r) {
        foreach ($r as $rr) {
            $selected = (($rr['profile_guid'] == $current) ? " selected=\"selected\" " : "");
            $o .= "<option value=\"{$rr['profile_guid']}\" $selected >{$rr['profile_name']}</option>\r\n";
        }
    }
    $o .= "</select>\r\n";
    return $o;
}

function contact_block()
{
    $o = '';

    if (! App::$profile['uid']) {
        return;
    }

    if (! perm_is_allowed(App::$profile['uid'], get_observer_hash(), 'view_contacts')) {
        return;
    }

    $shown = get_pconfig(App::$profile['uid'], 'system', 'display_friend_count');

    if ($shown === false) {
        $shown = 25;
    }
    if ($shown == 0) {
        return;
    }

    $is_owner = ((local_channel() && local_channel() == App::$profile['uid']) ? true : false);
    $sql_extra = '';

    $abook_flags = " and abook_pending = 0 and abook_self = 0 ";

    if (! $is_owner) {
        $abook_flags .= " and abook_hidden = 0 ";
        $sql_extra = " and xchan_hidden = 0 ";
    }

    if ((! is_array(App::$profile)) || (App::$profile['hide_friends'])) {
        return $o;
    }

    $r = q(
        "SELECT COUNT(abook_id) AS total FROM abook left join xchan on abook_xchan = xchan_hash WHERE abook_channel = %d
		$abook_flags and xchan_orphan = 0 and xchan_deleted = 0 $sql_extra",
        intval(App::$profile['uid'])
    );
    if (count($r)) {
        $total = intval($r[0]['total']);
    }
    if (! $total) {
        $contacts = t('No connections');
        $micropro = null;
    } else {
        $randfunc = db_getfunc('RAND');

        $r = q(
            "SELECT abook.*, xchan.* FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash WHERE abook_channel = %d $abook_flags and abook_archived = 0 and xchan_orphan = 0 and xchan_deleted = 0 $sql_extra ORDER BY $randfunc LIMIT %d",
            intval(App::$profile['uid']),
            intval($shown)
        );

        if (count($r)) {
            $contacts = t('Connections');
            $micropro = array();
            foreach ($r as $rr) {
                // There is no setting to discover if you are bi-directionally connected
                // Use the ability to post comments as an indication that this relationship is more
                // than wishful thinking; even though soapbox channels and feeds will disable it.

                if (! their_perms_contains(App::$profile['uid'], $rr['xchan_hash'], 'post_comments')) {
                    $rr['oneway'] = true;
                }
                $micropro[] = micropro($rr, true, 'mpfriend');
            }
        }
    }

    $tpl = get_markup_template('contact_block.tpl');
    $o = replace_macros($tpl, array(
        '$contacts' => $contacts,
        '$nickname' => App::$profile['channel_address'],
        '$viewconnections' => (($total > $shown) ? sprintf(t('View all %s connections'), $total) : ''),
        '$micropro' => $micropro,
    ));

    $arr = array('contacts' => $r, 'output' => $o);

    call_hooks('contact_block_end', $arr);
    return $o;
}

function micropro($contact, $redirect = false, $class = '', $mode = false)
{

    if ($contact['click']) {
        $url = '#';
    } else {
        $url = chanlink_hash($contact['xchan_hash']);
    }


    $tpl = 'micropro_img.tpl';
    if ($mode === true) {
        $tpl = 'micropro_txt.tpl';
    }
    if ($mode === 'card') {
        $tpl = 'micropro_card.tpl';
    }

    return replace_macros(get_markup_template($tpl), array(
        '$click' => (($contact['click']) ? $contact['click'] : ''),
        '$class' => $class . (($contact['archived']) ? ' archived' : ''),
        '$oneway' => (($contact['oneway']) ? true : false),
        '$url' => $url,
        '$photo' => $contact['xchan_photo_s'],
        '$name' => $contact['xchan_name'],
        '$addr' => $contact['xchan_addr'],
        '$title' => $contact['xchan_name'] . ' [' . $contact['xchan_addr'] . ']',
        '$network' => sprintf(t('Network: %s'), $contact['xchan_network'])
    ));
}
