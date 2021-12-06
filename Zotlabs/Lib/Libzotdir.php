<?php

namespace Zotlabs\Lib;

use App;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Webfinger;
use Zotlabs\Lib\Zotfinger;

require_once('include/permissions.php');


class Libzotdir
{


    /**
     * Directories may come and go over time. We will need to check that our
     * directory server is still valid occasionally, and reset to something that
     * is if our directory has gone offline for any reason
     */

    public static function check_upstream_directory()
    {

        $directory = get_config('system', 'directory_server');

        // it's possible there is no directory server configured and the local hub is being used.
        // If so, default to preserving the absence of a specific server setting.

        $isadir = true;

        if ($directory) {
            $j = Zotfinger::exec($directory);
            if (array_path_exists('data/directory_mode', $j)) {
                if ($j['data']['directory_mode'] === 'normal') {
                    $isadir = false;
                }
            }
        }

        if (!$isadir) {
            set_config('system', 'directory_server', '');
        }
    }


    public static function get_directory_setting($observer, $setting)
    {


        if ($observer) {
            $ret = get_xconfig($observer, 'directory', $setting);
        } else {
            $ret = ((array_key_exists($setting, $_SESSION)) ? intval($_SESSION[$setting]) : false);
        }

        if ($ret === false) {
            $ret = get_config('directory', $setting);
            if ($ret === false) {
                $ret = (in_array($setting, ['globaldir', 'safemode', 'activedir']) ? 1 : 0);
            }
        }

        if ($setting === 'globaldir' && intval(get_config('system', 'localdir_hide'))) {
            $ret = 1;
        }

        return $ret;
    }

    /**
     * @brief Called by the directory_sort widget.
     */
    public static function dir_sort_links()
    {

        $safe_mode = 1;

        $observer = get_observer_hash();

        $safe_mode = self::get_directory_setting($observer, 'safemode');
        $globaldir = self::get_directory_setting($observer, 'globaldir');
        $pubforums = self::get_directory_setting($observer, 'chantype');
        $activedir = self::get_directory_setting($observer, 'activedir');

        $hide_local = intval(get_config('system', 'localdir_hide'));
        if ($hide_local) {
            $globaldir = 1;
        }

        // Build urls without order and pubforums so it's easy to tack on the changed value
        // Probably there's an easier way to do this

        $directory_sort_order = get_config('system', 'directory_sort_order');
        if (!$directory_sort_order) {
            $directory_sort_order = 'date';
        }

        $current_order = (($_REQUEST['order']) ? $_REQUEST['order'] : $directory_sort_order);
        $suggest = (($_REQUEST['suggest']) ? '&suggest=' . $_REQUEST['suggest'] : '');

        $url = 'directory?f=';

        $tmp = array_merge($_GET, $_POST);
        unset($tmp['suggest']);
        unset($tmp['pubforums']);
        unset($tmp['type']);
        unset($tmp['global']);
        unset($tmp['safe']);
        unset($tmp['active']);
        unset($tmp['req']);
        unset($tmp['f']);
        $q = http_build_query($tmp);
        $forumsurl = $url . (($q) ? '&' . $q : '') . $suggest;

        $o = replace_macros(get_markup_template('dir_sort_links.tpl'), [
            '$header' => t('Directory Options'),
            '$forumsurl' => $forumsurl,
            '$safemode' => array('safemode', t('Safe Mode'), $safe_mode, '', array(t('No'), t('Yes')), ' onchange=\'window.location.href="' . $forumsurl . '&safe="+(this.checked ? 1 : 0)\''),
            '$pubforums' => array('pubforums', t('Groups Only'), (($pubforums == 1) ? true : false), '', array(t('No'), t('Yes')), ' onchange=\'window.location.href="' . $forumsurl . '&type="+(this.checked ? 1 : 0)\''),
//          '$collections' => array('collections', t('Collections Only'),(($pubforums == 2) ? true : false),'',array(t('No'), t('Yes')),' onchange=\'window.location.href="' . $forumsurl . '&type="+(this.checked ? 2 : 0)\''),
            '$hide_local' => $hide_local,
            '$globaldir' => array('globaldir', t('This Website Only'), 1 - intval($globaldir), '', array(t('No'), t('Yes')), ' onchange=\'window.location.href="' . $forumsurl . '&global="+(this.checked ? 0 : 1)\''),
            '$activedir' => array('activedir', t('Recently Updated'), intval($activedir), '', array(t('No'), t('Yes')), ' onchange=\'window.location.href="' . $forumsurl . '&active="+(this.checked ? 1 : 0)\''),
        ]);

        return $o;
    }

    /**
     * @brief
     *
     * Given an update record, probe the channel, grab a zot-info packet and refresh/sync the data.
     *
     * Ignore updating records marked as deleted.
     *
     * If successful, sets ud_last in the DB to the current datetime for this
     * reddress/webbie.
     *
     * @param array $ud Entry from update table
     */

    public static function update_directory_entry($ud)
    {

        logger('update_directory_entry: ' . print_r($ud, true), LOGGER_DATA);

        if ($ud['ud_addr'] && (!($ud['ud_flags'] & UPDATE_FLAGS_DELETED))) {
            $success = false;

            $href = Webfinger::zot_url(punify($ud['ud_addr']));
            if ($href) {
                $zf = Zotfinger::exec($href);
            }
            if (is_array($zf) && array_path_exists('signature/signer', $zf) && $zf['signature']['signer'] === $href && intval($zf['signature']['header_valid'])) {
                $xc = Libzot::import_xchan($zf['data'], 0, $ud);
            } else {
                q(
                    "update updates set ud_last = '%s' where ud_addr = '%s'",
                    dbesc(datetime_convert()),
                    dbesc($ud['ud_addr'])
                );
            }
        }
    }


    /**
     * @brief Push local channel updates to a local directory server.
     *
     * This is called from Zotlabs/Daemon/Directory.php if a profile is to be pushed to the
     * directory and the local hub in this case is any kind of directory server.
     *
     * @param int $uid
     * @param bool $force
     */

    public static function local_dir_update($uid, $force)
    {


        logger('local_dir_update: uid: ' . $uid, LOGGER_DEBUG);

        $p = q(
            "select channel_hash, channel_address, channel_timezone, profile.* from profile left join channel on channel_id = uid where uid = %d and is_default = 1",
            intval($uid)
        );

        $profile = [];
        $profile['encoding'] = 'zot';

        if ($p) {
            $hash = $p[0]['channel_hash'];

            $profile['description'] = $p[0]['pdesc'];
            $profile['birthday'] = $p[0]['dob'];
            if ($age = age($p[0]['dob'], $p[0]['channel_timezone'], '')) {
                $profile['age'] = $age;
            }

            $profile['gender'] = $p[0]['gender'];
            $profile['marital'] = $p[0]['marital'];
            $profile['sexual'] = $p[0]['sexual'];
            $profile['locale'] = $p[0]['locality'];
            $profile['region'] = $p[0]['region'];
            $profile['postcode'] = $p[0]['postal_code'];
            $profile['country'] = $p[0]['country_name'];
            $profile['about'] = $p[0]['about'];
            $profile['homepage'] = $p[0]['homepage'];
            $profile['hometown'] = $p[0]['hometown'];

            if ($p[0]['keywords']) {
                $tags = [];
                $k = explode(' ', $p[0]['keywords']);
                if ($k) {
                    foreach ($k as $kk) {
                        if (trim($kk)) {
                            $tags[] = trim($kk);
                        }
                    }
                }

                if ($tags) {
                    $profile['keywords'] = $tags;
                }
            }

            $hidden = (1 - intval($p[0]['publish']));

            // logger('hidden: ' . $hidden);

            $r = q(
                "select xchan_hidden from xchan where xchan_hash = '%s' limit 1",
                dbesc($p[0]['channel_hash'])
            );

            if (intval($r[0]['xchan_hidden']) != $hidden) {
                $r = q(
                    "update xchan set xchan_hidden = %d where xchan_hash = '%s'",
                    intval($hidden),
                    dbesc($p[0]['channel_hash'])
                );
            }

            $arr = ['channel_id' => $uid, 'hash' => $hash, 'profile' => $profile];
            call_hooks('local_dir_update', $arr);

            $address = channel_reddress($p[0]);

            if (perm_is_allowed($uid, '', 'view_profile')) {
                self::import_directory_profile($hash, $arr['profile'], $address, 0);
            } else {
                // they may have made it private
                $r = q(
                    "delete from xprof where xprof_hash = '%s'",
                    dbesc($hash)
                );
                $r = q(
                    "delete from xtag where xtag_hash = '%s'",
                    dbesc($hash)
                );
            }
        }

        $ud_hash = random_string() . '@' . App::get_hostname();
        self::update_modtime($hash, $ud_hash, channel_reddress($p[0]), (($force) ? UPDATE_FLAGS_FORCED : UPDATE_FLAGS_UPDATED));
    }


    /**
     * @brief Imports a directory profile.
     *
     * @param string $hash
     * @param array $profile
     * @param string $addr
     * @param number $ud_flags (optional) UPDATE_FLAGS_UPDATED
     * @param number $suppress_update (optional) default 0
     * @return bool $updated if something changed
     */

    public static function import_directory_profile($hash, $profile, $addr, $ud_flags = UPDATE_FLAGS_UPDATED, $suppress_update = 0)
    {

        logger('import_directory_profile', LOGGER_DEBUG);
        if (!$hash) {
            return false;
        }


        $maxlen = get_max_import_size();

        if ($maxlen && mb_strlen($profile['about']) > $maxlen) {
            $profile['about'] = mb_substr($profile['about'], 0, $maxlen, 'UTF-8');
        }

        $arr = [];

        $arr['xprof_hash'] = $hash;
        $arr['xprof_dob'] = ((isset($profile['birthday']) && $profile['birthday'] === '0000-00-00') ? $profile['birthday'] : datetime_convert('', '', $profile['birthday'], 'Y-m-d')); // !!!! check this for 0000 year
        $arr['xprof_age'] = (isset($profile['age']) ? intval($profile['age']) : 0);
        $arr['xprof_desc'] = ((isset($profile['description']) && $profile['description']) ? htmlspecialchars($profile['description'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_gender'] = ((isset($profile['gender']) && $profile['gender']) ? htmlspecialchars($profile['gender'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_marital'] = ((isset($profile['marital']) && $profile['marital']) ? htmlspecialchars($profile['marital'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_sexual'] = ((isset($profile['sexual']) && $profile['sexual']) ? htmlspecialchars($profile['sexual'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_locale'] = ((isset($profile['locale']) && $profile['locale']) ? htmlspecialchars($profile['locale'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_region'] = ((isset($profile['region']) && $profile['region']) ? htmlspecialchars($profile['region'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_postcode'] = ((isset($profile['postcode']) && $profile['postcode']) ? htmlspecialchars($profile['postcode'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_country'] = ((isset($profile['country']) && $profile['country']) ? htmlspecialchars($profile['country'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_about'] = ((isset($profile['about']) && $profile['about']) ? htmlspecialchars($profile['about'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_pronouns'] = ((isset($profile['pronouns']) && $profile['pronouns']) ? htmlspecialchars($profile['pronouns'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_homepage'] = ((isset($profile['homepage']) && $profile['homepage']) ? htmlspecialchars($profile['homepage'], ENT_COMPAT, 'UTF-8', false) : '');
        $arr['xprof_hometown'] = ((isset($profile['hometown']) && $profile['hometown']) ? htmlspecialchars($profile['hometown'], ENT_COMPAT, 'UTF-8', false) : '');

        $clean = [];
        if (array_key_exists('keywords', $profile) and is_array($profile['keywords'])) {
            self::import_directory_keywords($hash, $profile['keywords']);
            foreach ($profile['keywords'] as $kw) {
                $kw = trim(htmlspecialchars($kw, ENT_COMPAT, 'UTF-8', false));
                $kw = trim($kw, ',');
                $clean[] = $kw;
            }
        }

        $arr['xprof_keywords'] = implode(' ', $clean);

        // Self censored, make it so
        // These are not translated, so the German "erwachsenen" keyword will not censor the directory profile. Only the English form - "adult".


        if (in_arrayi('nsfw', $clean) || in_arrayi('adult', $clean)) {
            q(
                "update xchan set xchan_selfcensored = 1 where xchan_hash = '%s'",
                dbesc($hash)
            );
        }

        $r = q(
            "select * from xprof where xprof_hash = '%s' limit 1",
            dbesc($hash)
        );

        if ($arr['xprof_age'] > 150) {
            $arr['xprof_age'] = 150;
        }
        if ($arr['xprof_age'] < 0) {
            $arr['xprof_age'] = 0;
        }

        if ($r) {
            $update = false;
            foreach ($r[0] as $k => $v) {
                if ((array_key_exists($k, $arr)) && ($arr[$k] != $v)) {
                    logger('import_directory_profile: update ' . $k . ' => ' . $arr[$k]);
                    $update = true;
                    break;
                }
            }
            if ($update) {
                q(
                    "update xprof set
					xprof_desc = '%s',
					xprof_dob = '%s',
					xprof_age = %d,
					xprof_gender = '%s',
					xprof_marital = '%s',
					xprof_sexual = '%s',
					xprof_locale = '%s',
					xprof_region = '%s',
					xprof_postcode = '%s',
					xprof_country = '%s',
					xprof_about = '%s',
					xprof_homepage = '%s',
					xprof_hometown = '%s',
					xprof_keywords = '%s',
					xprof_pronouns = '%s'
					where xprof_hash = '%s'",
                    dbesc($arr['xprof_desc']),
                    dbesc($arr['xprof_dob']),
                    intval($arr['xprof_age']),
                    dbesc($arr['xprof_gender']),
                    dbesc($arr['xprof_marital']),
                    dbesc($arr['xprof_sexual']),
                    dbesc($arr['xprof_locale']),
                    dbesc($arr['xprof_region']),
                    dbesc($arr['xprof_postcode']),
                    dbesc($arr['xprof_country']),
                    dbesc($arr['xprof_about']),
                    dbesc($arr['xprof_homepage']),
                    dbesc($arr['xprof_hometown']),
                    dbesc($arr['xprof_keywords']),
                    dbesc($arr['xprof_pronouns']),
                    dbesc($arr['xprof_hash'])
                );
            }
        } else {
            $update = true;
            logger('New profile');
            q(
                "insert into xprof (xprof_hash, xprof_desc, xprof_dob, xprof_age, xprof_gender, xprof_marital, xprof_sexual, xprof_locale, xprof_region, xprof_postcode, xprof_country, xprof_about, xprof_homepage, xprof_hometown, xprof_keywords, xprof_pronouns) values ('%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s') ",
                dbesc($arr['xprof_hash']),
                dbesc($arr['xprof_desc']),
                dbesc($arr['xprof_dob']),
                intval($arr['xprof_age']),
                dbesc($arr['xprof_gender']),
                dbesc($arr['xprof_marital']),
                dbesc($arr['xprof_sexual']),
                dbesc($arr['xprof_locale']),
                dbesc($arr['xprof_region']),
                dbesc($arr['xprof_postcode']),
                dbesc($arr['xprof_country']),
                dbesc($arr['xprof_about']),
                dbesc($arr['xprof_homepage']),
                dbesc($arr['xprof_hometown']),
                dbesc($arr['xprof_keywords']),
                dbesc($arr['xprof_pronouns'])
            );
        }

        $d = [
            'xprof' => $arr,
            'profile' => $profile,
            'update' => $update
        ];

        /**
         * @hooks import_directory_profile
         *   Called when processing delivery of a profile structure from an external source (usually for directory storage).
         *   * \e array \b xprof
         *   * \e array \b profile
         *   * \e boolean \b update
         */

        call_hooks('import_directory_profile', $d);

        if (($d['update']) && (!$suppress_update)) {
            self::update_modtime($arr['xprof_hash'], new_uuid(), $addr, $ud_flags);
        }

        q(
            "update xchan set xchan_updated = '%s' where xchan_hash = '%s'",
            dbesc(datetime_convert()),
            dbesc($arr['xprof_hash'])
        );

        return $d['update'];
    }

    /**
     * @brief
     *
     * @param string $hash An xtag_hash
     * @param array $keywords
     */

    public static function import_directory_keywords($hash, $keywords)
    {

        $existing = [];
        $r = q(
            "select * from xtag where xtag_hash = '%s' and xtag_flags = 0",
            dbesc($hash)
        );

        if ($r) {
            foreach ($r as $rr) {
                $existing[] = $rr['xtag_term'];
            }
        }

        $clean = [];
        foreach ($keywords as $kw) {
            $kw = trim(htmlspecialchars($kw, ENT_COMPAT, 'UTF-8', false));
            $kw = trim($kw, ',');
            $clean[] = $kw;
        }

        foreach ($existing as $x) {
            if (!in_array($x, $clean)) {
                $r = q(
                    "delete from xtag where xtag_hash = '%s' and xtag_term = '%s' and xtag_flags = 0",
                    dbesc($hash),
                    dbesc($x)
                );
            }
        }
        foreach ($clean as $x) {
            if (!in_array($x, $existing)) {
                $r = q(
                    "insert into xtag ( xtag_hash, xtag_term, xtag_flags) values ( '%s' ,'%s', 0 )",
                    dbesc($hash),
                    dbesc($x)
                );
            }
        }
    }


    /**
     * @brief
     *
     * @param string $hash
     * @param string $guid
     * @param string $addr
     * @param int $flags (optional) default 0
     */

    public static function update_modtime($hash, $guid, $addr, $flags = 0)
    {

        $dirmode = intval(get_config('system', 'directory_mode'));

        if ($dirmode == DIRECTORY_MODE_NORMAL) {
            return;
        }

        if ($flags) {
            q(
                "insert into updates (ud_hash, ud_guid, ud_date, ud_flags, ud_addr ) values ( '%s', '%s', '%s', %d, '%s' )",
                dbesc($hash),
                dbesc($guid),
                dbesc(datetime_convert()),
                intval($flags),
                dbesc($addr)
            );
        } else {
            q(
                "update updates set ud_flags = ( ud_flags | %d ) where ud_addr = '%s' and (ud_flags & %d) = 0 ",
                intval(UPDATE_FLAGS_UPDATED),
                dbesc($addr),
                intval(UPDATE_FLAGS_UPDATED)
            );
        }
    }
}
