<?php

namespace Code\Module;

use App;
use URLify;
use Code\Web\Controller;
use Code\Web\HTTPSig;
use Code\Lib\Libzot;
use Code\Lib\Connect;
use Code\Lib\Channel;
use Code\Daemon\Run;
use Code\Import\Friendica;
use Code\Lib\ServiceClass;
use Code\Extend\Hook;
use Code\Render\Theme;


require_once('include/import.php');
require_once('include/photo_factory.php');


/**
 * @brief Module for channel import.
 *
 * Import a channel, either by direct file upload or via
 * connection to another server.
 */
class Import extends Controller
{

    /**
     * @brief Import channel into account.
     *
     * @param int $account_id
     */
    public function import_account($account_id)
    {

        if (!$account_id) {
            logger('No account ID supplied');
            return;
        }

        $max_friends = ServiceClass::account_fetch($account_id, 'total_channels');
        $max_feeds = ServiceClass::account_fetch($account_id, 'total_feeds');
        $data = null;
        $seize = ((x($_REQUEST, 'make_primary')) ? intval($_REQUEST['make_primary']) : 0);
        $import_posts = ((x($_REQUEST, 'import_posts')) ? intval($_REQUEST['import_posts']) : 0);
        $moving = false; // intval($_REQUEST['moving']);
        $src = $_FILES['filename']['tmp_name'];
        $filename = basename($_FILES['filename']['name']);
        $filesize = intval($_FILES['filename']['size']);
        $filetype = $_FILES['filename']['type'];
        $newname = trim(strtolower($_REQUEST['newname']));

        // import channel from file
        if ($src) {
            // This is OS specific and could also fail if your tmpdir isn't very
            // large mostly used for Diaspora which exports gzipped files.

            if (strpos($filename, '.gz')) {
                @rename($src, $src . '.gz');
                @system('gunzip ' . escapeshellarg($src . '.gz'));
            }

            if ($filesize) {
                $data = @file_get_contents($src);
            }
            unlink($src);
        }

        // import channel from another server
        if (!$src) {
            $old_address = ((x($_REQUEST, 'old_address')) ? $_REQUEST['old_address'] : '');
            if (!$old_address) {
                logger('Nothing to import.');
                notice(t('Nothing to import.') . EOL);
                return;
            } elseif (strpos($old_address, '＠')) {
                // if you copy the identity address from your profile page, make it work for convenience - WARNING: this is a utf-8 variant and NOT an ASCII ampersand. Please do not edit.
                $old_address = str_replace('＠', '@', $old_address);
            }

            $email = ((x($_REQUEST, 'email')) ? $_REQUEST['email'] : '');
            $password = ((x($_REQUEST, 'password')) ? $_REQUEST['password'] : '');

            $channelname = substr($old_address, 0, strpos($old_address, '@'));
            $servername = substr($old_address, strpos($old_address, '@') + 1);

            $api_path = probe_api_path($servername);
            if (!$api_path) {
                notice(t('Unable to download data from old server') . EOL);
                return;
            }

            $api_path .= 'channel/export/basic?f=&zap_compat=1&channel=' . $channelname;
            if ($import_posts) {
                $api_path .= '&posts=1';
            }
            $binary = false;
            $redirects = 0;
            $opts = ['http_auth' => $email . ':' . $password];
            $ret = z_fetch_url($api_path, $binary, $redirects, $opts);
            if ($ret['success']) {
                $data = $ret['body'];
            } else {
                notice(t('Unable to download data from old server') . EOL);
                return;
            }
        }

        if (!$data) {
            logger('Empty import file.');
            notice(t('Imported file is empty.') . EOL);
            return;
        }

        $data = json_decode($data, true);

        //logger('import: data: ' . print_r($data,true));
        //print_r($data);


        // handle Friendica export

        if (array_path_exists('user/parent-uid', $data)) {
            $settings = ['account_id' => $account_id, 'sieze' => 1, 'newname' => $newname];
            $f = new Friendica($data, $settings);

            return;
        }

        if (!array_key_exists('compatibility', $data)) {
            Hook::call('import_foreign_channel_data', $data);
            if ($data['handled']) {
                return;
            }
        }

        $codebase = 'zap';

        if ((!array_path_exists('compatibility/codebase', $data)) || $data['compatibility']['codebase'] !== $codebase) {
            notice('Data export format is not compatible with this software');
            return;
        }

        if ($moving) {
            $seize = 1;
        }

        // import channel

        $relocate = ((array_key_exists('relocate', $data)) ? $data['relocate'] : null);

        if (array_key_exists('channel', $data)) {
            $max_identities = ServiceClass::account_fetch($account_id, 'total_identities');

            if ($max_identities !== false) {
                $r = q(
                    "select channel_id from channel where channel_account_id = %d and channel_removed = 0 ",
                    intval($account_id)
                );
                if ($r && count($r) > $max_identities) {
                    notice(sprintf(t('Your service plan only allows %d channels.'), $max_identities) . EOL);
                    return;
                }
            }

            if ($newname) {
                $x = false;

                if (get_config('system', 'unicode_usernames')) {
                    $x = punify(mb_strtolower($newname));
                }

                if ((!$x) || strlen($x) > 64) {
                    $x = strtolower(URLify::transliterate($newname));
                } else {
                    $x = $newname;
                }
                $newname = $x;
            }

            $channel = import_channel($data['channel'], $account_id, $seize, $newname);
        } else {
            $moving = false;
            $channel = App::get_channel();
        }

        if (!$channel) {
            logger('Channel not found. ' . print_r($channel, true));
            notice(t('No channel. Import failed.') . EOL);
            return;
        }

        if (is_array($data['config'])) {
            import_config($channel, $data['config']);
        }

        logger('import step 2');

        if (array_key_exists('channel', $data)) {
            if (isset($data['photo']) && $data['photo']) {
                import_channel_photo(base64url_decode($data['photo']['data']), $data['photo']['type'], $account_id, $channel['channel_id']);
            }

            if (is_array($data['profile'])) {
                import_profiles($channel, $data['profile']);
            }
        }

        logger('import step 3');

        // import xchans and contact photos
        // This *must* be done before importing hublocs

        if (array_key_exists('channel', $data) && $seize) {
            // replace any existing xchan we may have on this site if we're seizing control

            $r = q(
                "delete from xchan where xchan_hash = '%s'",
                dbesc($channel['channel_hash'])
            );

            $r = xchan_store_lowlevel(
                [
                    'xchan_hash' => $channel['channel_hash'],
                    'xchan_guid' => $channel['channel_guid'],
                    'xchan_guid_sig' => $channel['channel_guid_sig'],
                    'xchan_pubkey' => $channel['channel_pubkey'],
                    'xchan_photo_l' => z_root() . "/photo/profile/l/" . $channel['channel_id'],
                    'xchan_photo_m' => z_root() . "/photo/profile/m/" . $channel['channel_id'],
                    'xchan_photo_s' => z_root() . "/photo/profile/s/" . $channel['channel_id'],
                    'xchan_addr' => Channel::get_webfinger($channel),
                    'xchan_url' => z_root() . '/channel/' . $channel['channel_address'],
                    'xchan_connurl' => z_root() . '/poco/' . $channel['channel_address'],
                    'xchan_follow' => z_root() . '/follow?f=&url=%s',
                    'xchan_name' => $channel['channel_name'],
                    'xchan_network' => 'nomad',
                    'xchan_updated' => datetime_convert(),
                    'xchan_photo_date' => datetime_convert(),
                    'xchan_name_date' => datetime_convert()
                ]
            );
        }

        logger('import step 4');

        // import xchans
        $xchans = $data['xchan'];
        if ($xchans) {
            foreach ($xchans as $xchan) {
                // Provide backward compatibility for zot11 based projects

                if ($xchan['xchan_network'] === 'nomad' && version_compare(ZOT_REVISION, '10.0') <= 0) {
                    $xchan['xchan_network'] = 'zot6';
                }

                $hash = Libzot::make_xchan_hash($xchan['xchan_guid'], $xchan['xchan_pubkey']);

                if (in_array($xchan['xchan_network'], ['nomad', 'zot6']) && $hash !== $xchan['xchan_hash']) {
                    logger('forged xchan: ' . print_r($xchan, true));
                    continue;
                }

                $r = q(
                    "select xchan_hash from xchan where xchan_hash = '%s' limit 1",
                    dbesc($xchan['xchan_hash'])
                );
                if ($r) {
                    continue;
                }
                xchan_store_lowlevel($xchan);


                if ($xchan['xchan_hash'] === $channel['channel_hash']) {
                    $r = q(
                        "update xchan set xchan_updated = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s' where xchan_hash = '%s'",
                        dbesc(datetime_convert()),
                        dbesc(z_root() . '/photo/profile/l/' . $channel['channel_id']),
                        dbesc(z_root() . '/photo/profile/m/' . $channel['channel_id']),
                        dbesc(z_root() . '/photo/profile/s/' . $channel['channel_id']),
                        dbesc($xchan['xchan_hash'])
                    );
                } else {
                    $photos = import_remote_xchan_photo($xchan['xchan_photo_l'], $xchan['xchan_hash']);
                    if ($photos) {
                        if ($photos[4]) {
                            $photodate = NULL_DATE;
                        } else {
                            $photodate = $xchan['xchan_photo_date'];
                        }

                        $r = q(
                            "update xchan set xchan_updated = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s', xchan_photo_date = '%s' where xchan_hash = '%s'",
                            dbesc(datetime_convert()),
                            dbesc($photos[0]),
                            dbesc($photos[1]),
                            dbesc($photos[2]),
                            dbesc($photos[3]),
                            dbesc($photodate),
                            dbesc($xchan['xchan_hash'])
                        );
                    }
                }
            }

            logger('import step 5');
        }


        logger('import step 6');


        if (is_array($data['hubloc'])) {
            import_hublocs($channel, $data['hubloc'], $seize, $moving);
        }

        logger('import step 7');

        // create new hubloc for the new channel at this site

        if (array_key_exists('channel', $data)) {
            $r = hubloc_store_lowlevel(
                [
                    'hubloc_guid' => $channel['channel_guid'],
                    'hubloc_guid_sig' => $channel['channel_guid_sig'],
                    'hubloc_id_url' => Channel::url($channel),
                    'hubloc_hash' => $channel['channel_hash'],
                    'hubloc_addr' => Channel::get_webfinger($channel),
                    'hubloc_network' => 'nomad',
                    'hubloc_primary' => (($seize) ? 1 : 0),
                    'hubloc_url' => z_root(),
                    'hubloc_url_sig' => Libzot::sign(z_root(), $channel['channel_prvkey']),
                    'hubloc_site_id' => Libzot::make_xchan_hash(z_root(), get_config('system', 'pubkey')),
                    'hubloc_host' => App::get_hostname(),
                    'hubloc_callback' => z_root() . '/zot',
                    'hubloc_sitekey' => get_config('system', 'pubkey'),
                    'hubloc_updated' => datetime_convert()
                ]
            );

            // reset the original primary hubloc if it is being seized

            if ($seize) {
                $r = q(
                    "update hubloc set hubloc_primary = 0 where hubloc_primary = 1 and hubloc_hash = '%s' and hubloc_url != '%s' ",
                    dbesc($channel['channel_hash']),
                    dbesc(z_root())
                );
            }
        }


        $friends = 0;
        $feeds = 0;

        // import contacts
        $abooks = $data['abook'];
        if ($abooks) {
            foreach ($abooks as $abook) {
                $abook_copy = $abook;

                $abconfig = null;
                if (array_key_exists('abconfig', $abook) && is_array($abook['abconfig']) && count($abook['abconfig'])) {
                    $abconfig = $abook['abconfig'];
                }

                unset($abook['abook_id']);
                unset($abook['abook_rating']);
                unset($abook['abook_rating_text']);
                unset($abook['abconfig']);
                unset($abook['abook_their_perms']);
                unset($abook['abook_my_perms']);
                unset($abook['abook_not_here']);

                $abook['abook_account'] = $account_id;
                $abook['abook_channel'] = $channel['channel_id'];

                $reconnect = false;

                if (array_key_exists('abook_instance', $abook) && $abook['abook_instance'] && strpos($abook['abook_instance'], z_root()) === false) {
                    $abook['abook_not_here'] = 1;
                    if (!($abook['abook_pending'] || $abook['abook_blocked'])) {
                        $reconnect = true;
                    }
                }

                if ($abook['abook_self']) {
                    $ctype = 0;
                    $role = get_pconfig($channel['channel_id'], 'system', 'permissions_role');
                    if (strpos($role, 'collection' !== false)) {
                        $ctype = 2;
                    } elseif (strpos($role, 'group') !== false) {
                        $ctype = 1;
                    }
                    if ($ctype) {
                        q(
                            "update xchan set xchan_type = %d where xchan_hash = '%s' ",
                            intval($ctype),
                            dbesc($abook['abook_xchan'])
                        );
                    }
                } else {
                    if ($max_friends !== false && $friends > $max_friends) {
                        continue;
                    }
                    if ($max_feeds !== false && intval($abook['abook_feed']) && ($feeds > $max_feeds)) {
                        continue;
                    }
                }

                $r = q(
                    "select abook_id from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
                    dbesc($abook['abook_xchan']),
                    intval($channel['channel_id'])
                );
                if ($r) {
                    $columns = db_columns('abook');

                    foreach ($abook as $k => $v) {
                        if (!in_array($k, $columns)) {
                            continue;
                        }
                        $r = q(
                            "UPDATE abook SET " . TQUOT . "%s" . TQUOT . " = '%s' WHERE abook_xchan = '%s' AND abook_channel = %d",
                            dbesc($k),
                            dbesc($v),
                            dbesc($abook['abook_xchan']),
                            intval($channel['channel_id'])
                        );
                    }
                } else {
                    abook_store_lowlevel($abook);

                    $friends++;
                    if (intval($abook['abook_feed'])) {
                        $feeds++;
                    }
                }

                if ($abconfig) {
                    foreach ($abconfig as $abc) {
                        set_abconfig($channel['channel_id'], $abc['xchan'], $abc['cat'], $abc['k'], $abc['v']);
                    }
                }
                if ($reconnect) {
                    Connect::connect($channel, $abook['abook_xchan']);
                }
            }

            logger('import step 8');
        }

        // import groups
        $groups = $data['group'];
        if ($groups) {
            $saved = [];
            foreach ($groups as $group) {
                $saved[$group['hash']] = ['old' => $group['id']];
                if (array_key_exists('name', $group)) {
                    $group['gname'] = $group['name'];
                    unset($group['name']);
                }
                $r = q("select * from pgrp where gname = '%s' and uid = %d",
                    dbesc($group['gname']),
                    intval($channel['channel_id'])
                );
                if ($r) {
                    continue;
                }
                unset($group['id']);
                $group['uid'] = $channel['channel_id'];

                create_table_from_array('pgrp', $group);
            }
            // create a list of ids that applies to this system so we can map members to them
            $r = q(
                "select * from pgrp where uid = %d",
                intval($channel['channel_id'])
            );
            if ($r) {
                foreach ($r as $rr) {
                    $saved[$rr['hash']]['new'] = $rr['id'];
                }
            }
        }

        // import group members
        $group_members = $data['group_member'];
        if ($group_members) {
            foreach ($group_members as $group_member) {
                unset($group_member['id']);
                $group_member['uid'] = $channel['channel_id'];
                foreach ($saved as $x) {
                    if ($x['old'] == $group_member['gid']) {
                        $group_member['gid'] = $x['new'];
                    }
                }
                // check if it's a duplicate
                $r = q("select * from pgrp_member where xchan = '%s' and gid = %d",
                    dbesc($group_member['xchan']),
                    intval($group_member['gid'])
                );
                if ($r) {
                    continue;
                }
                create_table_from_array('pgrp_member', $group_member);
            }
        }

        logger('import step 9');


        if (is_array($data['atoken'])) {
            import_atoken($channel, $data['atoken']);
        }
        if (is_array($data['xign'])) {
            import_xign($channel, $data['xign']);
        }
        if (is_array($data['block'])) {
            import_block($channel, $data['block']);
        }
        if (is_array($data['block_xchan'])) {
            import_xchans($data['block_xchan']);
        }
        if (is_array($data['obj'])) {
            import_objs($channel, $data['obj']);
        }
        if (is_array($data['likes'])) {
            import_likes($channel, $data['likes']);
        }
        if (is_array($data['app'])) {
            import_apps($channel, $data['app']);
        }
        if (is_array($data['sysapp'])) {
            import_sysapps($channel, $data['sysapp']);
        }
        if (is_array($data['chatroom'])) {
            import_chatrooms($channel, $data['chatroom']);
        }
//      if (is_array($data['conv'])) {
//          import_conv($channel,$data['conv']);
//      }
//      if (is_array($data['mail'])) {
//          import_mail($channel,$data['mail']);
//      }
        if (is_array($data['event'])) {
            import_events($channel, $data['event']);
        }
        if (is_array($data['event_item'])) {
            import_items($channel, $data['event_item'], false, $relocate);
        }
//      if (is_array($data['menu'])) {
//          import_menus($channel,$data['menu']);
//      }
//      if (is_array($data['wiki'])) {
//          import_items($channel,$data['wiki'],false,$relocate);
//      }
//      if (is_array($data['webpages'])) {
//          import_items($channel,$data['webpages'],false,$relocate);
//      }
        $addon = array('channel' => $channel, 'data' => $data);
        Hook::call('import_channel', $addon);

        $saved_notification_flags = Channel::notifications_off($channel['channel_id']);
        if ($import_posts && array_key_exists('item', $data) && $data['item']) {
            import_items($channel, $data['item'], false, $relocate);
        }

        if ($api_path && $import_posts) {  // we are importing from a server and not a file
            $m = parse_url($api_path);

            $hz_server = $m['scheme'] . '://' . $m['host'];

            $since = datetime_convert(date_default_timezone_get(), date_default_timezone_get(), '0001-01-01 00:00');
            $until = datetime_convert(date_default_timezone_get(), date_default_timezone_get(), 'now + 1 day');

            $poll_interval = get_config('system', 'poll_interval', 3);
            $page = 0;

            while (1) {
                $headers = [
                    'X-API-Token' => random_string(),
                    'X-API-Request' => $hz_server . '/api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page,
                    'Host' => $m['host'],
                    '(request-target)' => 'get /api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page,
                ];

                $headers = HTTPSig::create_sig($headers, $channel['channel_prvkey'], Channel::url($channel), true, 'sha512');

                $x = z_fetch_url($hz_server . '/api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page, false, $redirects, ['headers' => $headers]);

                // logger('z_fetch: ' . print_r($x,true));

                if (!$x['success']) {
                    logger('no API response');
                    break;
                }

                $j = json_decode($x['body'], true);

                if (!$j) {
                    break;
                }

                if (!(isset($j['item']) && is_array($j['item']) && count($j['item']))) {
                    break;
                }

                Run::Summon(['Content_importer', sprintf('%d', $page), $since, $until, $channel['channel_address'], urlencode($hz_server)]);
                sleep($poll_interval);

                $page++;
                continue;
            }

            $headers = [
                'X-API-Token' => random_string(),
                'X-API-Request' => $hz_server . '/api/z/1.0/files?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until),
                'Host' => $m['host'],
                '(request-target)' => 'get /api/z/1.0/files?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until),
            ];

            $headers = HTTPSig::create_sig($headers, $channel['channel_prvkey'], Channel::url($channel), true, 'sha512');

            $x = z_fetch_url($hz_server . '/api/z/1.0/files?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until), false, $redirects, ['headers' => $headers]);

            if (!$x['success']) {
                logger('no API response');
                return;
            }

            $j = json_decode($x['body'], true);

            if (!$j) {
                return;
            }

            if (!$j['success']) {
                return;
            }

            $poll_interval = get_config('system', 'poll_interval', 3);

            if (count($j['results'])) {
                $todo = count($j['results']);
                logger('total to process: ' . $todo, LOGGER_DEBUG);

                foreach ($j['results'] as $jj) {
                    Run::Summon(['File_importer', $jj['hash'], $channel['channel_address'], urlencode($hz_server)]);
                    sleep($poll_interval);
                }
            }

            notice(t('Files and Posts imported.') . EOL);
        }

        Channel::notifications_on($channel['channel_id'], $saved_notification_flags);


        // send out refresh requests
        // notify old server that it may no longer be primary.

        Run::Summon(['Notifier', 'refresh_all', $channel['channel_id']]);

        // This will indirectly perform a refresh_all *and* update the directory

        Run::Summon(['Directory', $channel['channel_id']]);

        notice(t('Import completed.') . EOL);

        change_channel($channel['channel_id']);

        goaway(z_root() . '/stream');
    }

    /**
     * @brief Handle POST action on channel import page.
     */

    public function post()
    {
        $account_id = get_account_id();
        if (!$account_id) {
            return;
        }

        check_form_security_token_redirectOnErr('/import', 'channel_import');
        $this->import_account($account_id);
    }

    /**
     * @brief Generate channel import page.
     *
     * @return string with parsed HTML.
     */

    public function get()
    {

        if (!get_account_id()) {
            notice(t('You must be logged in to use this feature.') . EOL);
            return EMPTY_STR;
        }

        return replace_macros(Theme::get_template('channel_import.tpl'), [
            '$title' => t('Import Channel'),
            '$desc' => t('Use this form to import an existing channel from a different server. You may retrieve the channel identity from the old server via the network or provide an export file.'),
            '$label_filename' => t('File to Upload'),
            '$choice' => t('Or provide the old server details'),
            '$old_address' => ['old_address', t('Your old identity address (xyz@example.com)'), '', ''],
            '$email' => ['email', t('Your old login email address'), '', ''],
            '$password' => ['password', t('Your old login password'), '', ''],
            '$import_posts' => ['import_posts', t('Import a few months of posts if possible (limited by available memory)'), false, '', [t('No'), t('Yes')]],

            '$common' => t('For either option, please choose whether to make this hub your new primary address, or whether your old location should continue this role. You will be able to post from either location, but only one can be marked as the primary location for files, photos, and media.'),

            '$make_primary' => ['make_primary', t('Make this hub my primary location'), false, '', [t('No'), t('Yes')]],
            '$moving' => ['moving', t('Move this channel (disable all previous locations)'), false, '', [t('No'), t('Yes')]],
            '$newname' => ['newname', t('Use this channel nickname instead of the one provided'), '', t('Leave blank to keep your existing channel nickname. You will be randomly assigned a similar nickname if either name is already allocated on this site.')],

            '$pleasewait' => t('This process may take several minutes to complete and considerably longer if importing a large amount of posts and files. Please submit the form only once and leave this page open until finished.'),

            '$form_security_token' => get_form_security_token('channel_import'),
            '$submit' => t('Submit')
        ]);
    }
}
