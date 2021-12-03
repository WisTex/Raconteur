<?php

use Zotlabs\Lib\Libzot;
use Zotlabs\Web\HTTPSig;

function xchan_store_lowlevel($arr)
{

    $store = [
        'xchan_hash' => ((array_key_exists('xchan_hash', $arr)) ? $arr['xchan_hash'] : ''),
        'xchan_guid' => ((array_key_exists('xchan_guid', $arr)) ? $arr['xchan_guid'] : ''),
        'xchan_guid_sig' => ((array_key_exists('xchan_guid_sig', $arr)) ? $arr['xchan_guid_sig'] : ''),
        'xchan_pubkey' => ((array_key_exists('xchan_pubkey', $arr)) ? $arr['xchan_pubkey'] : ''),
        'xchan_photo_mimetype'  => ((array_key_exists('xchan_photo_mimetype', $arr)) ? $arr['xchan_photo_mimetype'] : ''),
        'xchan_photo_l'  => ((array_key_exists('xchan_photo_l', $arr)) ? $arr['xchan_photo_l'] : ''),
        'xchan_photo_m' => ((array_key_exists('xchan_photo_m', $arr)) ? $arr['xchan_photo_m'] : ''),
        'xchan_photo_s' => ((array_key_exists('xchan_photo_s', $arr)) ? $arr['xchan_photo_s'] : ''),
        'xchan_addr' => ((array_key_exists('xchan_addr', $arr)) ? $arr['xchan_addr'] : ''),
        'xchan_url' => ((array_key_exists('xchan_url', $arr)) ? $arr['xchan_url'] : ''),
        'xchan_connurl' => ((array_key_exists('xchan_connurl', $arr)) ? $arr['xchan_connurl'] : ''),
        'xchan_follow' => ((array_key_exists('xchan_follow', $arr)) ? $arr['xchan_follow'] : ''),
        'xchan_connpage' => ((array_key_exists('xchan_connpage', $arr)) ? $arr['xchan_connpage'] : ''),
        'xchan_name' => ((array_key_exists('xchan_name', $arr)) ? $arr['xchan_name'] : ''),
        'xchan_network' => ((array_key_exists('xchan_network', $arr)) ? $arr['xchan_network'] : ''),
        'xchan_created' => ((array_key_exists('xchan_created', $arr)) ? datetime_convert('UTC', 'UTC', $arr['xchan_created']) : datetime_convert()),
        'xchan_updated' => ((array_key_exists('xchan_updated', $arr)) ? datetime_convert('UTC', 'UTC', $arr['xchan_updated']) : datetime_convert()),
        'xchan_photo_date' => ((array_key_exists('xchan_photo_date', $arr)) ? datetime_convert('UTC', 'UTC', $arr['xchan_photo_date']) : NULL_DATE),
        'xchan_name_date' => ((array_key_exists('xchan_name_date', $arr)) ? datetime_convert('UTC', 'UTC', $arr['xchan_name_date']) : NULL_DATE),
        'xchan_hidden' => ((array_key_exists('xchan_hidden', $arr)) ? intval($arr['xchan_hidden']) : 0),
        'xchan_orphan' => ((array_key_exists('xchan_orphan', $arr)) ? intval($arr['xchan_orphan']) : 0),
        'xchan_censored' => ((array_key_exists('xchan_censored', $arr)) ? intval($arr['xchan_censored']) : 0),
        'xchan_selfcensored' => ((array_key_exists('xchan_selfcensored', $arr)) ? intval($arr['xchan_selfcensored']) : 0),
        'xchan_system' => ((array_key_exists('xchan_system', $arr)) ? intval($arr['xchan_system']) : 0),
        'xchan_type' => ((array_key_exists('xchan_type', $arr)) ? intval($arr['xchan_type']) : XCHAN_TYPE_PERSON),
        'xchan_deleted' => ((array_key_exists('xchan_deleted', $arr)) ? intval($arr['xchan_deleted']) : 0)
    ];

    return create_table_from_array('xchan', $store);
}

// called from the zot api
// Anybody can enter an identity into the system, but zot or zot6 identities must be validated
// and existing zot6 identities cannot be altered via API

function xchan_store($arr)
{

    $update_photo = false;
    $update_name  = false;

    if (! ($arr['guid'] || $arr['hash'])) {
        $arr = json_decode(file_get_contents('php://input'), true);
    }

    logger('xchan_store: ' . print_r($arr, true));

    if (! $arr['hash']) {
        $arr['hash'] = $arr['guid'];
    }
    if (! $arr['hash']) {
        return false;
    }

    $r = q(
        "select * from xchan where xchan_hash = '%s' limit 1",
        dbesc($arr['hash'])
    );
    if (! $r) {
        $update_photo = true;

        if (! $arr['network']) {
            $arr['network'] = 'unknown';
        }
        if (! $arr['name']) {
            $arr['name'] = 'unknown';
        }
        if (! $arr['url']) {
            $arr['url'] = z_root();
        }
        if (! $arr['photo']) {
            $arr['photo'] = z_root() . '/' . get_default_profile_photo();
        }


        if ($arr['network'] === 'zot6') {
            if ((! $arr['key']) || (! Libzot::verify($arr['id'], $arr['id_sig'], $arr['key']))) {
                logger('Unable to verify signature for ' . $arr['hash']);
                return false;
            }
        }

        if ($arr['network'] === 'zot') {
            if ((! $arr['key']) || (! Libzot::verify($arr['id'], 'sha256.' . $arr['id_sig'], $arr['key']))) {
                logger('Unable to verify signature for ' . $arr['hash']);
                return false;
            }
        }

        $columns = db_columns('xchan');

        $x = [];
        foreach ($arr as $k => $v) {
            if ($k === 'key') {
                $x['xchan_pubkey'] = HTTPSig::convertKey(escape_tags($v));
                continue;
            }
            if ($k === 'photo') {
                continue;
            }

            if (in_array($columns, 'xchan_' . $k)) {
                $x['xchan_' . $k] = escape_tags($v);
            }
        }

        $x['xchan_updated']    = datetime_convert();
        $x['xchan_name_date']  = datetime_convert();
        $x['xchan_photo_date'] = datetime_convert();
        $x['xchan_system']     = false;

        $result = xchan_store_lowlevel($x);

        if (! $result) {
            return $result;
        }
    } else {
        if ($r[0]['network'] === 'zot6') {
            return true;
        }
        if ($r[0]['xchan_photo_date'] < datetime_convert('UTC', 'UTC', $arr['photo_date'])) {
            $update_photo = true;
        }
        if ($r[0]['xchan_name_date'] < datetime_convert('UTC', 'UTC', $arr['name_date'])) {
            $update_name = true;
        }
    }

    if ($update_photo && $arr['photo']) {
        $photos = import_remote_xchan_photo($arr['photo'], $arr['hash']);
        if ($photos) {
            $x = q(
                "update xchan set xchan_updated = '%s', xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s'",
                dbesc(datetime_convert()),
                dbesc(datetime_convert()),
                dbesc($photos[0]),
                dbesc($photos[1]),
                dbesc($photos[2]),
                dbesc($photos[3]),
                dbesc($arr['hash'])
            );
        }
    }
    if ($update_name && $arr['name']) {
        $x = q(
            "update xchan set xchan_updated = '%s', xchan_name = '%s', xchan_name_date = '%s' where xchan_hash = '%s'",
            dbesc(datetime_convert()),
            dbesc(escape_tags($arr['name'])),
            dbesc(datetime_convert()),
            dbesc($arr['hash'])
        );
    }

    return $true;
}


function xchan_match($arr)
{

    if (! $arr) {
        return false;
    }

    $str = '';

    foreach ($arr as $k => $v) {
        if ($str) {
            $str .= " AND ";
        }
        $str .= " " . TQUOT . dbesc($k) . TQUOT . " = '" . dbesc($v) . "' ";
    }

    $r = q("select * from xchan where $str limit 1");

    return (($r) ? $r[0] : false);
}





function xchan_fetch($arr)
{


    $key = '';
    if ($arr['hash']) {
        $key = 'xchan_hash';
        $v = dbesc($arr['hash']);
    } elseif ($arr['guid']) {
        $key = 'xchan_guid';
        $v = dbesc($arr['guid']);
    } elseif ($arr['address']) {
        $key = 'xchan_addr';
        $v = dbesc($arr['address']);
    }

    if (! $key) {
        return false;
    }

    $r = q("select * from xchan where $key = '$v' limit 1");
    if (! $r) {
        return false;
    }

    $ret = [];
    foreach ($r[0] as $k => $v) {
        if ($k === 'xchan_addr') {
            $ret['address'] = $v;
        } else {
            $ret[str_replace('xchan_', '', $k)] = $v;
        }
    }

    return $ret;
}


function xchan_keychange_table($table, $column, $oldxchan, $newxchan)
{
    $r = q(
        "update $table set $column = '%s' where $column = '%s'",
        dbesc($newxchan['xchan_hash']),
        dbesc($oldxchan['xchan_hash'])
    );
    return $r;
}

function xchan_keychange_acl($table, $column, $oldxchan, $newxchan)
{

    $allow = (($table === 'channel') ? 'channel_allow_cid' : 'allow_cid');
    $deny  = (($table === 'channel') ? 'channel_deny_cid'  : 'deny_cid');


    $r = q(
        "select $column, $allow, $deny from $table where ($allow like '%s' or $deny like '%s') ",
        dbesc('<' . $oldxchan['xchan_hash'] . '>'),
        dbesc('<' . $oldxchan['xchan_hash'] . '>')
    );

    if ($r) {
        foreach ($r as $rv) {
            $z = q(
                "update $table set $allow = '%s', $deny = '%s' where $column = %d",
                dbesc(str_replace(
                    '<' . $oldxchan['xchan_hash'] . '>',
                    '<' . $newxchan['xchan_hash'] . '>',
                    $rv[$allow]
                )),
                dbesc(str_replace(
                    '<' . $oldxchan['xchan_hash'] . '>',
                    '<' . $newxchan['xchan_hash'] . '>',
                    $rv[$deny]
                )),
                intval($rv[$column])
            );
        }
    }
    return $z;
}


function xchan_change_key($oldx, $newx, $data)
{

    $tables = [
        'abook'        => 'abook_xchan',
        'abconfig'     => 'xchan',
        'pgrp_member'  => 'xchan',
        'chat'         => 'chat_xchan',
        'chatpresence' => 'cp_xchan',
        'event'        => 'event_xchan',
        'item'         => 'owner_xchan',
        'item'         => 'author_xchan',
        'item'         => 'source_xchan',
        'mail'         => 'from_xchan',
        'mail'         => 'to_xchan',
        'shares'       => 'share_xchan',
        'source'       => 'src_channel_xchan',
        'source'       => 'src_xchan',
        'xchat'        => 'xchat_xchan',
        'xconfig'      => 'xchan',
        'xign'         => 'xchan',
        'xlink'        => 'xlink_xchan',
        'xprof'        => 'xprof_hash',
        'xtag'         => 'xtag_hash'
    ];


    $acls = [
        'channel'   => 'channel_id',
        'attach'    => 'id',
        'chatroom'  => 'cr_id',
        'event'     => 'id',
        'item'      => 'id',
        'menu_item' => 'mitem_id',
        'obj'       => 'obj_id',
        'photo'     => 'id'
    ];


    foreach ($tables as $k => $v) {
        xchan_keychange_table($k, $v, $oldx, $newx);
    }

    foreach ($acls as $k => $v) {
        xchan_keychange_acl($k, $v, $oldx, $newx);
    }
}


function migrate_xchan_photos($limit = 100)
{

    $r = q(
        "select xchan_photo_l, xchan_hash, photo.xchan, photo.resource_id from photo left join xchan on photo.xchan = xchan_hash where photo.xchan != '' and uid = 0 and imgscale = 4 and photo_usage = 2 and xchan_photo_l like ('%s') limit %d",
        dbesc(z_root() . '/photo/%'),
        intval($limit)
    );
    if ($r) {
        foreach ($r as $rv) {
            logger('migrating xchan_photo for ' . $rv['xchan_hash']);
            $photos = import_remote_xchan_photo($rv['xchan_photo_l'], $rv['xchan_hash']);
            if ($photos) {
                $r = q(
                    "update xchan set xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s'
					where xchan_hash = '%s'",
                    dbesc($photos[0]),
                    dbesc($photos[1]),
                    dbesc($photos[2]),
                    dbesc($photos[3]),
                    dbesc($rv['xchan_hash'])
                );
            }
        }
    }
}




function cleanup_xchan_photos($limit = 500)
{

    $r = q(
        "select photo.xchan, photo.resource_id from photo left join xchan on photo.xchan = xchan_hash where photo.xchan != '' and uid = 0 and imgscale = 4 and photo_usage = 2 and xchan_photo_l like ('%s') limit %d",
        dbesc(z_root() . '/xp/%'),
        intval($limit)
    );
    if ($r) {
        foreach ($r as $rv) {
            q(
                "delete from photo where xchan = '%s' and resource_id = '%s' and photo_usage = 2 and uid = 0",
                dbesc($rv['xchan']),
                dbesc($rv['resource_id'])
            );
        }
    }
}


function xprof_store_lowlevel($profile)
{

    if (! $profile['hash']) {
        return false;
    }

    $store = [
        $arr['xprof_hash']         => $profile['hash'],
        $arr['xprof_dob']          => (($profile['birthday'] === '0000-00-00') ? $profile['birthday'] : datetime_convert('', '', $profile['birthday'], 'Y-m-d')),
        $arr['xprof_age']          => (($profile['age'])         ? intval($profile['age']) : 0),
        $arr['xprof_desc']         => (($profile['description']) ? htmlspecialchars($profile['description'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_gender']       => (($profile['gender'])      ? htmlspecialchars($profile['gender'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_marital']      => (($profile['marital'])     ? htmlspecialchars($profile['marital'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_sexual']       => (($profile['sexual'])      ? htmlspecialchars($profile['sexual'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_locale']       => (($profile['locale'])      ? htmlspecialchars($profile['locale'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_region']       => (($profile['region'])      ? htmlspecialchars($profile['region'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_postcode']     => (($profile['postcode'])    ? htmlspecialchars($profile['postcode'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_country']      => (($profile['country'])     ? htmlspecialchars($profile['country'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_about']        => (($profile['about'])       ? htmlspecialchars($profile['about'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_homepage']     => (($profile['homepage'])    ? htmlspecialchars($profile['homepage'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_hometown']     => (($profile['hometown'])    ? htmlspecialchars($profile['hometown'], ENT_COMPAT, 'UTF-8', false) : ''),
        $arr['xprof_keywords']     => (($profile['keywords'])    ? htmlspecialchars($profile['keywords'], ENT_COMPAT, 'UTF-8', false) : ''),

    ];

    return create_table_from_array('xchan', $store);
}
