<?php

namespace Code\Lib;

use App;
use Code\Lib\Libsync;
use Code\Lib\Channel;
use Code\Lib\Features;
use Code\Extend\Hook;
use Code\Lib\Addon;
use Code\Render\Theme;

    
/**
 * Apps
 *
 */
class Apps
{

    public static $available_apps = null;
    public static $installed_apps = null;

    public static $base_apps = null;


    public static function get_system_apps($translate = true)
    {

        $ret = [];
        if (is_dir('apps')) {
            $files = glob('apps/*.apd');
        } else {
            $files = glob('app/*.apd');
        }
        if ($files) {
            foreach ($files as $f) {
                $x = self::parse_app_description($f, $translate);
                if ($x) {
                    $ret[] = $x;
                }
            }
        }
        $files = glob('addon/*/*.apd');
        if ($files) {
            foreach ($files as $f) {
                $path = explode('/', $f);
                $plugin = trim($path[1]);
                if (Addon::is_installed($plugin)) {
                    $x = self::parse_app_description($f, $translate);
                    if ($x) {
                        $x['plugin'] = $plugin;
                        $ret[] = $x;
                    }
                }
            }
        }

        Hook::call('get_system_apps', $ret);

        return $ret;
    }

    public static function get_base_apps()
    {

        // to add additional default "base" apps to your site, put their English name, one per line,
        // into 'cache/default_apps'. This will be merged with the default project base apps.

        if (file_exists('cache/default_apps')) {
            $custom_apps = file('cache/default_apps', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            // do some cleanup in case the file was edited by hand and contains accidentally introduced whitespace
            if (is_array($custom_apps) && $custom_apps) {
                $custom_apps = array_map('trim', $custom_apps);
            }
        }

        $default_apps = [
            'Admin',
            'Channel Home',
            'Connections',
            'Directory',
            'Events',
            'Files',
            'Help',
            'Lists',
            'Photos',
            'Profile Photo',
            'Search',
            'Settings',
            'Stream',
            'Suggest Channels',
            'View Profile'
        ];
        if (is_array($custom_apps)) {
            $default_apps = array_values(array_unique(array_merge($default_apps, $custom_apps)));
        }

        $x = get_config('system', 'base_apps', $default_apps);
        Hook::call('get_base_apps', $x);
        return $x;
    }

    public static function import_system_apps()
    {
        if (!local_channel()) {
            return;
        }

        self::$base_apps = self::get_base_apps();

        $apps = self::get_system_apps(false);

        self::$available_apps = q("select * from app where app_channel = 0");

        self::$installed_apps = q(
            "select * from app where app_channel = %d",
            intval(local_channel())
        );

        if ($apps) {
            foreach ($apps as $app) {
                $id = self::check_install_system_app($app);

                // $id will be boolean true or false to install an app, or an integer id to update an existing app
                if ($id !== false) {
                    $app['uid'] = 0;
                    $app['guid'] = hash('whirlpool', $app['name']);
                    $app['system'] = 1;
                    self::app_install(0, $app);
                }

                $id = self::check_install_personal_app($app);
                // $id will be boolean true or false to install an app, or an integer id to update an existing app
                if ($id === false) {
                    continue;
                }
                if ($id !== true) {
                    // if we already installed this app, but it changed, preserve any categories we created
                    $r = q(
                        "select term from term where otype = %d and oid = %d",
                        intval(TERM_OBJ_APP),
                        intval($id)
                    );
                    if ($r) {
                        $app['categories'] = array_elm_to_str($r, 'term');
                    }
                }
                $app['uid'] = local_channel();
                $app['guid'] = hash('whirlpool', $app['name']);
                $app['system'] = 1;
                self::app_install(local_channel(), $app, true);
            }
        }
    }

    /**
     * Install the system app if no system apps have been installed, or if a new system app
     * is discovered, or if the version of a system app changes.
     */

    public static function check_install_system_app($app)
    {
        if ((!is_array(self::$available_apps)) || (!count(self::$available_apps))) {
            return true;
        }
        $notfound = true;
        foreach (self::$available_apps as $iapp) {
            if ($iapp['app_id'] == hash('whirlpool', $app['name'])) {
                $notfound = false;
                if (
                    (isset($app['version']) && $iapp['app_version'] !== $app['version'])
                    || ((isset($app['plugin']) && $app['plugin']) && (!(isset($iapp['app_plugin']) && $iapp['app_plugin'])))
                ) {
                    return intval($iapp['app_id']);
                }

                if (
                    ($iapp['app_url'] !== $app['url'])
                    || ($iapp['app_photo'] !== $app['photo'])
                ) {
                    return intval($iapp['app_id']);
                }
            }
        }

        return $notfound;
    }


    /**
     * Install the system app if no system apps have been installed, or if a new system app
     * is discovered, or if the version of a system app changes.
     */

    public static function check_install_personal_app($app)
    {
        $installed = false;
        foreach (self::$installed_apps as $iapp) {
            if ($iapp['app_id'] == hash('whirlpool', $app['name'])) {
                $installed = true;
                if (
                    ($iapp['app_version'] != $app['version'])
                    || (isset($app['plugin']) && $app['plugin'] && (!(isset($iapp['app_plugin']) && $iapp['app_plugin'])))
                ) {
                    return intval($iapp['app_id']);
                }
            }
        }
        if (!$installed && in_array($app['name'], self::$base_apps)) {
            return true;
        }
        return false;
    }


    public static function app_name_compare($a, $b)
    {
        return strcasecmp($a['name'], $b['name']);
    }


    public static function parse_app_description($f, $translate = true)
    {

        $ret = [];

        $baseurl = z_root();
        $channel = App::get_channel();
        $address = (($channel) ? $channel['channel_address'] : '');

        //future expansion

        $observer = App::get_observer();


        $lines = @file($f);
        if ($lines) {
            foreach ($lines as $x) {
                if (preg_match('/^([a-zA-Z].*?):(.*?)$/ism', $x, $matches)) {
                    $ret[$matches[1]] = trim($matches[2]);
                }
            }
        }

        if (!$ret['photo']) {
            $ret['photo'] = $baseurl . '/' . Channel::get_default_profile_photo(80);
        }

        $ret['type'] = 'system';

        foreach ($ret as $k => $v) {
            if (strpos($v, 'http') === 0) {
                if (!(local_channel() && strpos($v, z_root()) === 0)) {
                    $ret[$k] = zid($v);
                }
            }
        }

        if (array_key_exists('desc', $ret)) {
            $ret['desc'] = str_replace(array('\'', '"'), array('&#39;', '&dquot;'), $ret['desc']);
        }
        if (array_key_exists('target', $ret)) {
            $ret['target'] = str_replace(array('\'', '"'), array('&#39;', '&dquot;'), $ret['target']);
        }
        if (array_key_exists('version', $ret)) {
            $ret['version'] = str_replace(array('\'', '"'), array('&#39;', '&dquot;'), $ret['version']);
        }
        if (array_key_exists('categories', $ret)) {
            $ret['categories'] = str_replace(array('\'', '"'), array('&#39;', '&dquot;'), $ret['categories']);
        }
        if (array_key_exists('requires', $ret)) {
            $requires = explode(',', $ret['requires']);
            foreach ($requires as $require) {
                $require = trim(strtolower($require));
                $config = false;

                if (substr($require, 0, 7) == 'config:') {
                    $config = true;
                    $require = ltrim($require, 'config:');
                    $require = explode('=', $require);
                }

                switch ($require) {
                    case 'nologin':
                        if (local_channel()) {
                            unset($ret);
                        }
                        break;
                    case 'admin':
                        if (!is_site_admin()) {
                            unset($ret);
                        }
                        break;
                    case 'local_channel':
                        if (!local_channel()) {
                            unset($ret);
                        }
                        break;
                    case 'public_profile':
                        if (!Channel::is_public_profile()) {
                            unset($ret);
                        }
                        break;
                    case 'public_stream':
                        if (!can_view_public_stream()) {
                            unset($ret);
                        }
                        break;
                    case 'custom_role':
                        if (get_pconfig(local_channel(), 'system', 'permissions_role') !== 'custom') {
                            unset($ret);
                        }
                        break;
                    case 'observer':
                        if (!$observer) {
                            unset($ret);
                        }
                        break;
                    default:
                        if ($config) {
                            $unset = ((get_config('system', $require[0]) == $require[1]) ? false : true);
                        } else {
                            $unset = ((local_channel() && Features::enabled(local_channel(), $require)) ? false : true);
                        }
                        if ($unset) {
                            unset($ret);
                        }
                        break;
                }
            }
        }
        if (isset($ret)) {
            if ($translate) {
                self::translate_system_apps($ret);
            }
            return $ret;
        }
        return false;
    }


    public static function translate_system_apps(&$arr)
    {
        $apps = array(
            'Admin' => t('Site Admin'),
            'Apps' => t('Apps'),
            'Articles' => t('Articles'),
            'CalDAV' => t('CalDAV'),
            'CardDAV' => t('CardDAV'),
            'Cards' => t('Cards'),
            'Calendar' => t('Calendar'),
            'Categories' => t('Categories'),
            'Channel Home' => t('Channel Home'),
            'Channel Manager' => t('Channel Manager'),
            'Channel Sources' => t('Channel Sources'),
            'Chat' => t('Chat'),
            'Chatrooms' => t('Chatrooms'),
            'Clients' => t('Clients'),
            'Comment Control' => t('Comment Control'),
            'Communities' => t('Communities'),
            'Connections' => t('Connections'),
            'Content Filter' => t('Content Filter'),
            'Content Import' => t('Content Import'),
            'Custom SQL' => t('Custom SQL'),
            'Directory' => t('Directory'),
            'Drafts' => t('Drafts'),
            'Events' => t('Events'),
            'Expire Posts' => t('Expire Posts'),
            'Features' => t('Features'),
            'Files' => t('Files'),
            'Followlist' => t('Followlist'),
            'Friend Zoom' => t('Friend Zoom'),
            'Future Posting' => t('Future Posting'),
            'Gallery' => t('Gallery'),
            'Guest Pass' => t('Guest Pass'),
            'Help' => t('Help'),
            'Invite' => t('Invite'),
            'Language' => t('Language'),
            'Lists' => t('Lists'),
            'Login' => t('Login'),
            'Mail' => t('Mail'),
            'Markup' => t('Markup'),
            'Mood' => t('Mood'),
            'My Chatrooms' => t('My Chatrooms'),
            'No Comment' => t('No Comment'),
            'Notes' => t('Notes'),
            'Notifications' => t('Notifications'),
            'OAuth Apps Manager' => t('OAuth Apps Manager'),
            'OAuth2 Apps Manager' => t('OAuth2 Apps Manager'),
            'Order Apps' => t('Order Apps'),
            'PDL Editor' => t('PDL Editor'),
            'Permission Categories' => t('Permission Categories'),
            'Photos' => t('Photos'),
            'Photomap' => t('Photomap'),
            'Poke' => t('Poke'),
            'Post' => t('Post'),
            'Premium Channel' => t('Premium Channel'),
            'Probe' => t('Probe'),
            'Profile' => t('Profile'),
            'Profile Photo' => t('Profile Photo'),
            'Profiles' => t('Profiles'),
            'Public Stream' => t('Public Stream'),
            'Random Channel' => t('Random Channel'),
            'Remote Diagnostics' => t('Remote Diagnostics'),
            'Report Bug' => t('Report Bug'),
            'Roles' => t('Roles'),
            'Search' => t('Search'),
            'Secrets' => t('Secrets'),
            'Settings' => t('Settings'),
            'Sites' => t('Sites'),
            'Stream' => t('Stream'),
            'Stream Order' => t('Stream Order'),
            'Suggest' => t('Suggest'),
            'Suggest Channels' => t('Suggest Channels'),
            'Tagadelic' => t('Tagadelic'),
            'Tasks' => t('Tasks'),
            'View Bookmarks' => t('View Bookmarks'),
            'View Profile' => t('View Profile'),
            'Virtual Lists' => t('Virtual Lists'),
            'Webpages' => t('Webpages'),
            'Wiki' => t('Wiki'),
            'ZotPost' => t('ZotPost'),
        );

        if (array_key_exists('name', $arr)) {
            if (array_key_exists($arr['name'], $apps)) {
                $arr['name'] = $apps[$arr['name']];
            }
        } else {
            for ($x = 0; $x < count($arr); $x++) {
                if (array_key_exists($arr[$x]['name'], $apps)) {
                    $arr[$x]['name'] = $apps[$arr[$x]['name']];
                } else {
                    // Try to guess by app name if not in list
                    $arr[$x]['name'] = t(trim($arr[$x]['name']));
                }
            }
        }
    }


    // papp is a portable app

    public static function app_render($papp, $mode = 'view')
    {

        /**
         * modes:
         *    view: normal mode for viewing an app via bbcode from a conversation or page
         *       provides install/update button if you're logged in locally
         *    install: like view but does not display app-bin options if they are present
         *    list: normal mode for viewing an app on the app page
         *       no buttons are shown
         *    edit: viewing the app page in editing mode provides a delete button
         *    nav: render apps for app-bin
         */

		$channel_id = local_channel();
		$sys_channel = Channel::is_system($channel_id);

        $installed = false;

        if (!$papp) {
            return;
        }

        if (!$papp['photo']) {
            $papp['photo'] = 'icon:gear';
        }

        self::translate_system_apps($papp);

        if (isset($papp['plugin']) && trim($papp['plugin']) && (!Addon::is_installed(trim($papp['plugin'])))) {
            return '';
        }

        $papp['papp'] = self::papp_encode($papp);

        // This will catch somebody clicking on a system "available" app that hasn't had the path macros replaced
        // and they are allowed to see the app


        if (strpos($papp['url'], '$baseurl') !== false || strpos($papp['url'], '$nick') !== false || strpos($papp['photo'], '$baseurl') !== false || strpos($papp['photo'], '$nick') !== false) {
            $view_channel = $channel_id;
            if (!$view_channel) {
                $sys = Channel::get_system();
                $view_channel = $sys['channel_id'];
            }
            self::app_macros($view_channel, $papp);
        }

        if (strpos($papp['url'], ',')) {
            $urls = explode(',', $papp['url']);
            $papp['url'] = trim($urls[0]);
            $papp['settings_url'] = trim($urls[1]);
        }

        if (!strstr($papp['url'], '://')) {
            $papp['url'] = z_root() . ((strpos($papp['url'], '/') === 0) ? '' : '/') . $papp['url'];
        }


        foreach ($papp as $k => $v) {
            if (strpos($v, 'http') === 0 && $k != 'papp') {
                if (!($channel_id && strpos($v, z_root()) === 0)) {
                    $papp[$k] = zid($v);
                }
            }
            if ($k === 'desc') {
                $papp['desc'] = str_replace(array('\'', '"'), array('&#39;', '&dquot;'), $papp['desc']);
            }

            if ($k === 'requires') {
                $requires = explode(',', $v);

                foreach ($requires as $require) {
                    $require = trim(strtolower($require));
                    $config = false;

                    if (substr($require, 0, 7) == 'config:') {
                        $config = true;
                        $require = ltrim($require, 'config:');
                        $require = explode('=', $require);
                    }

                    switch ($require) {
                        case 'nologin':
                            if ($channel_id) {
                                return '';
                            }
                            break;
                        case 'admin':
                            if (!(is_site_admin() || $sys_channel)) {
                                return '';
                            }
                            break;
                        case 'local_channel':
                            if (!$channel_id) {
                                return '';
                            }
                            break;
                        case 'public_profile':
                            if (!Channel::is_public_profile()) {
                                return '';
                            }
                            break;
                        case 'public_stream':
                            if (!can_view_public_stream()) {
                                return '';
                            }
                            break;
                        case 'custom_role':
                            if (get_pconfig($channel_id, 'system', 'permissions_role') != 'custom') {
                                return '';
                            }
                            break;
                        case 'observer':
                            $observer = App::get_observer();
                            if (!$observer) {
                                return '';
                            }
                            break;
                        default:
                            if ($config) {
                                $unset = ((get_config('system', $require[0]) === $require[1]) ? false : true);
                            } else {
                                $unset = (($channel_id && Features::enabled($channnel_id, $require)) ? false : true);
                            }
                            if ($unset) {
                                return '';
                            }
                            break;
                    }
                }
            }
        }

        $hosturl = '';

        if ($channel_id || $sys_channel) {
            if (self::app_installed(($sys_channel) ? 0 : $channel_id, $papp)) {
                $installed = true;
                if ($mode === 'install') {
                    return '';
                }
            }
            $hosturl = z_root() . '/';
        } elseif (remote_channel()) {
            $observer = App::get_observer();
			if ($observer && in_array($observer['xchan_network'],['nomad','zot6'])) {
                // some folks might have xchan_url redirected offsite, use the connurl
                $x = parse_url($observer['xchan_connurl']);
                if ($x) {
                    $hosturl = $x['scheme'] . '://' . $x['host'] . '/';
                }
            }
        }

        $install_action = (($installed) ? t('Installed') : t('Install'));
        $icon = ((strpos($papp['photo'], 'icon:') === 0) ? substr($papp['photo'], 5) : '');

        if ($mode === 'navbar') {
            return replace_macros(Theme::get_template('app_nav.tpl'), [
                '$app' => $papp,
                '$icon' => $icon,
            ]);
        }

        if ($mode === 'install') {
            $papp['embed'] = true;
        }

        $featured = $pinned = false;
        if (isset($papp['categories'])) {
            $featured = ((strpos($papp['categories'], 'nav_featured_app') !== false) ? true : false);
            $pinned = ((strpos($papp['categories'], 'nav_pinned_app') !== false) ? true : false);
        }

        return replace_macros(Theme::get_template('app.tpl'), [
            '$app' => $papp,
            '$icon' => $icon,
            '$hosturl' => $hosturl,
            '$purchase' => ((isset($papp['page']) && $papp['page'] && (!$installed)) ? t('Purchase') : ''),
            '$installed' => $installed,
            '$action_label' => (($hosturl && in_array($mode, ['view', 'install'])) ? $install_action : ''),
            '$edit' => (($channel_id && $installed && $mode === 'edit') ? t('Edit') : ''),
            '$delete' => (($channel_id && $installed && $mode === 'edit') ? t('Delete') : ''),
            '$undelete' => (($channel_id && $installed && $mode === 'edit') ? t('Undelete') : ''),
            '$settings_url' => (($channel_id && $installed && $mode === 'list' && isset($papp['settings_url'])) ? $papp['settings_url'] : ''),
            '$deleted' => ((isset($papp['deleted'])) ? intval($papp['deleted']) : false),
            '$feature' => (((isset($papp['embed']) && $papp['embed']) || $mode === 'edit') ? false : true),
            '$pin' => (((isset($papp['embed']) && $papp['embed']) || $mode === 'edit') ? false : true),
            '$featured' => $featured,
            '$pinned' => $pinned,
            '$navapps' => (($mode === 'nav') ? true : false),
            '$order' => (($mode === 'nav-order' || $mode === 'nav-order-pinned') ? true : false),
            '$mode' => $mode,
            '$add' => t('Add to app-tray'),
            '$remove' => t('Remove from app-tray'),
            '$add_nav' => t('Pin to navbar'),
            '$remove_nav' => t('Unpin from navbar')
        ]);
    }

    public static function app_install($uid, $app, $sync = false)
    {

        if (!is_array($app)) {
            $r = q(
                "select * from app where app_name = '%s' and app_channel = 0",
                dbesc($app)
            );
            if (!$r) {
                return false;
            }

            $app = self::app_encode($r[0]);
        }

        $app['uid'] = $uid;

        if (self::app_installed($uid, $app, true)) {
			// preserve the existing deleted status across app updates
			if (isset($app['guid'])) {
				$check = q("select * from app where app_id = '%s' and app_channel = %d",
					dbesc($app['guid']),
					intval($uid)
				);
				if ($check) {
					$app['deleted'] = intval($check[0]['app_deleted']);
				}
			}
            $x = self::app_update($app);
        } else {
            $x = self::app_store($app);
        }

        if ($x['success']) {
            if ($sync && $app['uid']) {
                $r = q(
                    "select * from app where app_id = '%s' and app_channel = %d limit 1",
                    dbesc($x['app_id']),
                    intval($uid)
                );
                if ($r) {
                    if ($app['categories'] && (!$app['term'])) {
                        $r[0]['term'] = q(
                            "select * from term where otype = %d and oid = %d",
                            intval(TERM_OBJ_APP),
                            intval($r[0]['id'])
                        );
                    }
                    if (intval($r[0]['app_system'])) {
                        Libsync::build_sync_packet($uid, array('sysapp' => [$r[0]]));
                    } else {
                        Libsync::build_sync_packet($uid, array('app' => [$r[0]]));
                    }
                }
            }

            return $x['app_id'];
        }
        return false;
    }


    public static function can_delete($uid, $app)
    {
		// $uid 0 cannot delete, only archive

        if (!$uid) {
            return false;
        }

        $base_apps = self::get_base_apps();
        if ($base_apps) {
            foreach ($base_apps as $b) {
                if ($app['guid'] === hash('whirlpool', $b)) {
                    return false;
                }
            }
        }
        return true;
    }


    public static function app_destroy($uid, $app)
    {

        if ($app['guid']) {
            $x = q(
                "select * from app where app_id = '%s' and app_channel = %d limit 1",
                dbesc($app['guid']),
                intval($target_uid)
            );
            if ($x) {
                if (!intval($x[0]['app_deleted'])) {
                    $x[0]['app_deleted'] = 1;
                    if (self::can_delete($uid, $app)) {
                        $r = q(
                            "delete from app where app_id = '%s' and app_channel = %d",
                            dbesc($app['guid']),
                            intval($uid)
                        );
                        q(
                            "delete from term where otype = %d and oid = %d",
                            intval(TERM_OBJ_APP),
                            intval($x[0]['id'])
                        );
                        Hook::call('app_destroy', $x[0]);
                    } else {
                        $r = q(
                            "update app set app_deleted = 1 where app_id = '%s' and app_channel = %d",
                            dbesc($app['guid']),
                            intval($target_uid)
                        );
                    }
					if ($uid) {
	                    if (intval($x[0]['app_system'])) {
    	                    Libsync::build_sync_packet($uid, array('sysapp' => $x));
        	            } else {
            	            Libsync::build_sync_packet($uid, array('app' => $x));
                	    }
					}
                } else {
                    self::app_undestroy($uid, $app);
                }
            }
        }
    }

    public static function app_undestroy($uid, $app)
    {

        // undelete a system app

        if ($app['guid']) {
            $x = q(
                "select * from app where app_id = '%s' and app_channel = %d limit 1",
                dbesc($app['guid']),
                intval($uid)
            );
            if ($x) {
                if ($x[0]['app_system']) {
                    $r = q(
                        "update app set app_deleted = 0 where app_id = '%s' and app_channel = %d",
                        dbesc($app['guid']),
                        intval($uid)
                    );
                }
            }
        }
    }

    public static function app_feature($uid, $app, $term)
    {
        $r = q(
            "select id from app where app_id = '%s' and app_channel = %d limit 1",
            dbesc($app['guid']),
            intval($uid)
        );

        $x = q(
            "select * from term where otype = %d and oid = %d and term = '%s' limit 1",
            intval(TERM_OBJ_APP),
            intval($r[0]['id']),
            dbesc($term)
        );

        if ($x) {
            q(
                "delete from term where otype = %d and oid = %d and term = '%s'",
                intval(TERM_OBJ_APP),
                intval($x[0]['oid']),
                dbesc($term)
            );
        } else {
            store_item_tag($uid, $r[0]['id'], TERM_OBJ_APP, TERM_CATEGORY, $term, escape_tags(z_root() . '/apps/?f=&cat=' . $term));
        }
    }

    public static function app_installed($uid, $app, $bypass_filter = false)
    {
        $r = q(
            "select id from app where app_id = '%s' and app_channel = %d limit 1",
            dbesc((array_key_exists('guid', $app)) ? $app['guid'] : ''),
            intval($uid)
        );
        if (!$bypass_filter) {
            $filter_arr = [
                'uid' => $uid,
                'app' => $app,
                'installed' => $r
            ];
            Hook::call('app_installed_filter', $filter_arr);
            $r = $filter_arr['installed'];
        }

        return (($r) ? true : false);
    }

    public static function addon_app_installed($uid, $app, $bypass_filter = false)
    {

        $r = q(
            "select id from app where app_plugin = '%s' and app_channel = %d limit 1",
            dbesc($app),
            intval($uid)
        );
        if (!$bypass_filter) {
            $filter_arr = [
                'uid' => $uid,
                'app' => $app,
                'installed' => $r
            ];
            Hook::call('addon_app_installed_filter', $filter_arr);
            $r = $filter_arr['installed'];
        }

        return (($r) ? true : false);
    }

    public static function system_app_installed($uid, $app, $bypass_filter = false)
    {

        $r = q(
            "select id from app where app_id = '%s' and app_channel = %d and app_deleted = 0 limit 1",
            dbesc(hash('whirlpool', $app)),
            intval($uid)
        );
        if (!$bypass_filter) {
            $filter_arr = [
                'uid' => $uid,
                'app' => $app,
                'installed' => $r
            ];
            Hook::call('system_app_installed_filter', $filter_arr);
            $r = $filter_arr['installed'];
        }

        return (($r) ? true : false);
    }

    public static function app_list($uid, $deleted = false, $cats = [])
    {
        if ($deleted) {
            $sql_extra = "";
        } else {
            $sql_extra = " and app_deleted = 0 ";
        }
        if ($cats) {
            $cat_sql_extra = " and ( ";

            foreach ($cats as $cat) {
                if (strpos($cat_sql_extra, 'term')) {
                    $cat_sql_extra .= "or ";
                }

                $cat_sql_extra .= "term = '" . dbesc($cat) . "' ";
            }

            $cat_sql_extra .= ") ";

            $r = q(
                "select oid from term where otype = %d $cat_sql_extra",
                intval(TERM_OBJ_APP)
            );
            if (!$r) {
                return $r;
            }
            $sql_extra .= " and app.id in ( " . array_elm_to_str($r, 'oid') . ') ';
        }

        $r = q(
            "select * from app where app_channel = %d $sql_extra order by app_name asc",
            intval($uid)
        );

        if ($r) {
            $hookinfo = ['uid' => $uid, 'deleted' => $deleted, 'cats' => $cats, 'apps' => $r];
            Hook::call('app_list', $hookinfo);
            $r = $hookinfo['apps'];
            for ($x = 0; $x < count($r); $x++) {
                if (!$r[$x]['app_system']) {
                    $r[$x]['type'] = 'personal';
                }
                $r[$x]['term'] = q(
                    "select * from term where otype = %d and oid = %d",
                    intval(TERM_OBJ_APP),
                    intval($r[$x]['id'])
                );
            }
        }

        return ($r);
    }


    public static function app_search_available($str)
    {

        // not yet finished
        // somehow need to account for translations

        $r = q(
            "select * from app where app_channel = 0 $sql_extra order by app_name asc",
            intval($uid)
        );

        return ($r);
    }


    public static function app_order($uid, $apps, $menu)
    {

        if (!$apps) {
            return $apps;
        }

        $conf = (($menu === 'nav_featured_app') ? 'app_order' : 'app_pin_order');

        $x = (($uid) ? get_pconfig($uid, 'system', $conf) : get_config('system', $conf));
        if (($x) && (!is_array($x))) {
            $y = explode(',', $x);
            $y = array_map('trim', $y);
            $x = $y;
        }

        if (!(is_array($x) && ($x))) {
            return $apps;
        }

        $ret = [];
        foreach ($x as $xx) {
            $y = self::find_app_in_array($xx, $apps);
            if ($y) {
                $ret[] = $y;
            }
        }
        foreach ($apps as $ap) {
            if (!self::find_app_in_array($ap['name'], $ret)) {
                $ret[] = $ap;
            }
        }
        return $ret;
    }

    public static function find_app_in_array($name, $arr)
    {
        if (!$arr) {
            return false;
        }
        foreach ($arr as $x) {
            if ($x['name'] === $name) {
                return $x;
            }
        }
        return false;
    }

    public static function moveup($uid, $guid, $menu)
    {
        $syslist = [];

        $conf = (($menu === 'nav_featured_app') ? 'app_order' : 'app_pin_order');

        $list = self::app_list($uid, false, [$menu]);
        if ($list) {
            foreach ($list as $li) {
                $papp = self::app_encode($li);
                $syslist[] = $papp;
            }
        }
        self::translate_system_apps($syslist);

        usort($syslist, 'self::app_name_compare');

        $syslist = self::app_order($uid, $syslist, $menu);

        if (!$syslist) {
            return;
        }

        $newlist = [];

        foreach ($syslist as $k => $li) {
            if ($li['guid'] === $guid) {
                $position = $k;
                break;
            }
        }
        if (!$position) {
            return;
        }
        $dest_position = $position - 1;
        $saved = $syslist[$dest_position];
        $syslist[$dest_position] = $syslist[$position];
        $syslist[$position] = $saved;

        $narr = [];
        foreach ($syslist as $x) {
            $narr[] = $x['name'];
        }

        set_pconfig($uid, 'system', $conf, implode(',', $narr));
    }

    public static function movedown($uid, $guid, $menu)
    {
        $syslist = [];

        $conf = (($menu === 'nav_featured_app') ? 'app_order' : 'app_pin_order');

        $list = self::app_list($uid, false, [$menu]);
        if ($list) {
            foreach ($list as $li) {
                $papp = self::app_encode($li);
                $syslist[] = $papp;
            }
        }
        self::translate_system_apps($syslist);

        usort($syslist, 'self::app_name_compare');

        $syslist = self::app_order($uid, $syslist, $menu);

        if (!$syslist) {
            return;
        }

        $newlist = [];

        foreach ($syslist as $k => $li) {
            if ($li['guid'] === $guid) {
                $position = $k;
                break;
            }
        }
        if ($position >= count($syslist) - 1) {
            return;
        }
        $dest_position = $position + 1;
        $saved = $syslist[$dest_position];
        $syslist[$dest_position] = $syslist[$position];
        $syslist[$position] = $saved;

        $narr = [];
        foreach ($syslist as $x) {
            $narr[] = $x['name'];
        }

        set_pconfig($uid, 'system', $conf, implode(',', $narr));
    }

    public static function app_decode($s)
    {
        $x = base64_decode(str_replace(array('<br>', "\r", "\n", ' '), array('', '', '', ''), $s));
        return json_decode($x, true);
    }


    public static function app_macros($uid, &$arr)
    {

        if (!intval($uid)) {
            return;
        }

        $baseurl = z_root();
        $channel = Channel::from_id($uid);
        $address = (($channel) ? $channel['channel_address'] : '');

        // future expansion

        $observer = App::get_observer();

        $arr['url'] = str_replace(array('$baseurl', '$nick'), array($baseurl, $address), $arr['url']);
        $arr['photo'] = str_replace(array('$baseurl', '$nick'), array($baseurl, $address), $arr['photo']);
    }


    public static function app_store($arr)
    {

        // logger('app_store: ' . print_r($arr,true));

        $darray = [];
        $ret = ['success' => false];

        $sys = Channel::get_system();

        self::app_macros($arr['uid'], $arr);

        $darray['app_url'] = ((x($arr, 'url')) ? $arr['url'] : '');
        $darray['app_channel'] = ((x($arr, 'uid')) ? $arr['uid'] : 0);

        if (!$darray['app_url']) {
            return $ret;
        }

        if ((!$arr['uid']) && (!$arr['author'])) {
            $arr['author'] = $sys['channel_hash'];
        }

        if ($arr['photo'] && (strpos($arr['photo'], 'icon:') === false) && (strpos($arr['photo'], z_root()) === false)) {
            $x = import_remote_xchan_photo(str_replace('$baseurl', z_root(), $arr['photo']), get_observer_hash(), true);
            if ($x) {
                $arr['photo'] = $x[1];
            }
        }


        $darray['app_id'] = ((x($arr, 'guid')) ? $arr['guid'] : random_string() . '.' . App::get_hostname());
        $darray['app_sig'] = ((x($arr, 'sig')) ? $arr['sig'] : '');
        $darray['app_author'] = ((x($arr, 'author')) ? $arr['author'] : get_observer_hash());
        $darray['app_name'] = ((x($arr, 'name')) ? escape_tags($arr['name']) : t('Unknown'));
        $darray['app_desc'] = ((x($arr, 'desc')) ? escape_tags($arr['desc']) : '');
        $darray['app_photo'] = ((x($arr, 'photo')) ? $arr['photo'] : z_root() . '/' . Channel::get_default_profile_photo(80));
        $darray['app_version'] = ((x($arr, 'version')) ? escape_tags($arr['version']) : '');
        $darray['app_addr'] = ((x($arr, 'addr')) ? escape_tags($arr['addr']) : '');
        $darray['app_price'] = ((x($arr, 'price')) ? escape_tags($arr['price']) : '');
        $darray['app_page'] = ((x($arr, 'page')) ? escape_tags($arr['page']) : '');
        $darray['app_plugin'] = ((x($arr, 'plugin')) ? escape_tags(trim($arr['plugin'])) : '');
        $darray['app_requires'] = ((x($arr, 'requires')) ? escape_tags($arr['requires']) : '');
        $darray['app_system'] = ((x($arr, 'system')) ? intval($arr['system']) : 0);
        $darray['app_deleted'] = ((x($arr, 'deleted')) ? intval($arr['deleted']) : 0);
        $darray['app_options'] = ((x($arr, 'options')) ? intval($arr['options']) : 0);

        $created = datetime_convert();

        $r = q(
            "insert into app ( app_id, app_sig, app_author, app_name, app_desc, app_url, app_photo, app_version, app_channel, app_addr, app_price, app_page, app_requires, app_created, app_edited, app_system, app_plugin, app_deleted, app_options ) values ( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', %d, %d )",
            dbesc($darray['app_id']),
            dbesc($darray['app_sig']),
            dbesc($darray['app_author']),
            dbesc($darray['app_name']),
            dbesc($darray['app_desc']),
            dbesc($darray['app_url']),
            dbesc($darray['app_photo']),
            dbesc($darray['app_version']),
            intval($darray['app_channel']),
            dbesc($darray['app_addr']),
            dbesc($darray['app_price']),
            dbesc($darray['app_page']),
            dbesc($darray['app_requires']),
            dbesc($created),
            dbesc($created),
            intval($darray['app_system']),
            dbesc($darray['app_plugin']),
            intval($darray['app_deleted']),
            intval($darray['app_options'])
        );

        if ($r) {
            $ret['success'] = true;
            $ret['app_id'] = $darray['app_id'];
        }

        if ($arr['categories']) {
            $x = q(
                "select id from app where app_id = '%s' and app_channel = %d limit 1",
                dbesc($darray['app_id']),
                intval($darray['app_channel'])
            );
            $y = explode(',', $arr['categories']);
            if ($y) {
                foreach ($y as $t) {
                    $t = trim($t);
                    if ($t) {
                        store_item_tag($darray['app_channel'], $x[0]['id'], TERM_OBJ_APP, TERM_CATEGORY, escape_tags($t), escape_tags(z_root() . '/apps/?f=&cat=' . escape_tags($t)));
                    }
                }
            }
        }

        return $ret;
    }


    public static function app_update($arr)
    {

        // logger('app_update: ' . print_r($arr,true));
        $darray = [];
        $ret = ['success' => false];

        self::app_macros($arr['uid'], $arr);


        $darray['app_url'] = ((x($arr, 'url')) ? $arr['url'] : '');
        $darray['app_channel'] = ((x($arr, 'uid')) ? $arr['uid'] : 0);
        $darray['app_id'] = ((x($arr, 'guid')) ? $arr['guid'] : 0);

        if ((!$darray['app_url']) || (!$darray['app_id'])) {
            return $ret;
        }

        if ($arr['photo'] && (strpos($arr['photo'], 'icon:') === false) && (strpos($arr['photo'], z_root()) === false)) {
            $x = import_remote_xchan_photo(str_replace('$baseurl', z_root(), $arr['photo']), get_observer_hash(), true);
            if ($x) {
                $arr['photo'] = $x[1];
            }
        }

        $darray['app_sig'] = ((x($arr, 'sig')) ? $arr['sig'] : '');
        $darray['app_author'] = ((x($arr, 'author')) ? $arr['author'] : get_observer_hash());
        $darray['app_name'] = ((x($arr, 'name')) ? escape_tags($arr['name']) : t('Unknown'));
        $darray['app_desc'] = ((x($arr, 'desc')) ? escape_tags($arr['desc']) : '');
        $darray['app_photo'] = ((x($arr, 'photo')) ? $arr['photo'] : z_root() . '/' . Channel::get_default_profile_photo(80));
        $darray['app_version'] = ((x($arr, 'version')) ? escape_tags($arr['version']) : '');
        $darray['app_addr'] = ((x($arr, 'addr')) ? escape_tags($arr['addr']) : '');
        $darray['app_price'] = ((x($arr, 'price')) ? escape_tags($arr['price']) : '');
        $darray['app_page'] = ((x($arr, 'page')) ? escape_tags($arr['page']) : '');
        $darray['app_plugin'] = ((x($arr, 'plugin')) ? escape_tags(trim($arr['plugin'])) : '');
        $darray['app_requires'] = ((x($arr, 'requires')) ? escape_tags($arr['requires']) : '');
        $darray['app_system'] = ((x($arr, 'system')) ? intval($arr['system']) : 0);
        $darray['app_deleted'] = ((x($arr, 'deleted')) ? intval($arr['deleted']) : 0);
        $darray['app_options'] = ((x($arr, 'options')) ? intval($arr['options']) : 0);

        $edited = datetime_convert();

        $r = q(
            "update app set app_sig = '%s', app_author = '%s', app_name = '%s', app_desc = '%s', app_url = '%s', app_photo = '%s', app_version = '%s', app_addr = '%s', app_price = '%s', app_page = '%s', app_requires = '%s', app_edited = '%s', app_system = %d, app_plugin = '%s', app_deleted = %d, app_options = %d where app_id = '%s' and app_channel = %d",
            dbesc($darray['app_sig']),
            dbesc($darray['app_author']),
            dbesc($darray['app_name']),
            dbesc($darray['app_desc']),
            dbesc($darray['app_url']),
            dbesc($darray['app_photo']),
            dbesc($darray['app_version']),
            dbesc($darray['app_addr']),
            dbesc($darray['app_price']),
            dbesc($darray['app_page']),
            dbesc($darray['app_requires']),
            dbesc($edited),
            intval($darray['app_system']),
            dbesc($darray['app_plugin']),
            intval($darray['app_deleted']),
            intval($darray['app_options']),
            dbesc($darray['app_id']),
            intval($darray['app_channel'])
        );
        if ($r) {
            $ret['success'] = true;
            $ret['app_id'] = $darray['app_id'];
        }

        $x = q(
            "select id from app where app_id = '%s' and app_channel = %d limit 1",
            dbesc($darray['app_id']),
            intval($darray['app_channel'])
        );

        // if updating an embed app and we don't have a 0 channel_id don't mess with any existing categories

        if (array_key_exists('embed', $arr) && intval($arr['embed']) && (intval($darray['app_channel']))) {
            return $ret;
        }

        if ($x) {
            q(
                "delete from term where otype = %d and oid = %d",
                intval(TERM_OBJ_APP),
                intval($x[0]['id'])
            );
            if (isset($arr['categories']) && $arr['categories']) {
                $y = explode(',', $arr['categories']);
                if ($y) {
                    foreach ($y as $t) {
                        $t = trim($t);
                        if ($t) {
                            store_item_tag($darray['app_channel'], $x[0]['id'], TERM_OBJ_APP, TERM_CATEGORY, escape_tags($t), escape_tags(z_root() . '/apps/?f=&cat=' . escape_tags($t)));
                        }
                    }
                }
            }
        }

        return $ret;
    }


    public static function app_encode($app, $embed = false)
    {

        $ret = [];

        $ret['type'] = 'personal';

        if (isset($app['app_id']) && $app['app_id']) {
            $ret['guid'] = $app['app_id'];
        }
        if (isset($app['app_sig']) && $app['app_sig']) {
            $ret['sig'] = $app['app_sig'];
        }
        if (isset($app['app_author']) && $app['app_author']) {
            $ret['author'] = $app['app_author'];
        }
        if (isset($app['app_name']) && $app['app_name']) {
            $ret['name'] = $app['app_name'];
        }
        if (isset($app['app_desc']) && $app['app_desc']) {
            $ret['desc'] = $app['app_desc'];
        }
        if (isset($app['app_url']) && $app['app_url']) {
            $ret['url'] = $app['app_url'];
        }
        if (isset($app['app_photo']) && $app['app_photo']) {
            $ret['photo'] = $app['app_photo'];
        }
        if (isset($app['app_icon']) && $app['app_icon']) {
            $ret['icon'] = $app['app_icon'];
        }
        if (isset($app['app_version']) && $app['app_version']) {
            $ret['version'] = $app['app_version'];
        }
        if (isset($app['app_addr']) && $app['app_addr']) {
            $ret['addr'] = $app['app_addr'];
        }
        if (isset($app['app_price']) && $app['app_price']) {
            $ret['price'] = $app['app_price'];
        }
        if (isset($app['app_page']) && $app['app_page']) {
            $ret['page'] = $app['app_page'];
        }
        if (isset($app['app_requires']) && $app['app_requires']) {
            $ret['requires'] = $app['app_requires'];
        }
        if (isset($app['app_system']) && $app['app_system']) {
            $ret['system'] = $app['app_system'];
        }
        if (isset($app['app_options']) && $app['app_options']) {
            $ret['options'] = $app['app_options'];
        }
        if (isset($app['app_plugin']) && $app['app_plugin']) {
            $ret['plugin'] = trim($app['app_plugin']);
        }
        if (isset($app['app_deleted']) && $app['app_deleted']) {
            $ret['deleted'] = $app['app_deleted'];
        }
        if (isset($app['term']) && $app['term']) {
            $ret['categories'] = array_elm_to_str($app['term'], 'term');
        }


        if (!$embed) {
            return $ret;
        }
        $ret['embed'] = true;

        if (array_key_exists('categories', $ret)) {
            unset($ret['categories']);
        }

        $j = json_encode($ret);
        return '[app]' . chunk_split(base64_encode($j), 72, "\n") . '[/app]';
    }


    public static function papp_encode($papp)
    {
        return chunk_split(base64_encode(json_encode($papp)), 72, "\n");
    }
}
