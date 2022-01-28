<?php

namespace Zotlabs\Lib;

use App;
use Zotlabs\Lib\Channel;
use URLify;

class System
{

    public static function get_platform_name()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && array_key_exists('platform_name', App::$config['system'])) {
            return App::$config['system']['platform_name'];
        }
        return PLATFORM_NAME;
    }

    public static function get_site_name()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['sitename']) {
            return App::$config['system']['sitename'];
        }
        return '';
    }

    public static function get_project_name()
    {
        $name = self::get_site_name();
        if ($name) {
            $words = explode(' ', $name);
            $project = strtolower(URLify::transliterate($words[0]));
        }
        else {
            $project = self::get_platform_name();
        }
        return $project;
    }

    
    public static function get_banner()
    {

        if (is_array(App::$config) && is_array(App::$config['system']) && array_key_exists('banner', App::$config['system']) && App::$config['system']['banner']) {
            return App::$config['system']['banner'];
        }
        return self::get_site_name();
    }

    public static function get_project_icon()
    {
        $sys = Channel::get_system();
        if ($sys) {
            return z_root() . '/photo/profile/l/' . $sys['channel_id'];
        }
        if (is_array(App::$config) && is_array(App::$config['system']) && array_key_exists('icon', App::$config['system'])) {
            return App::$config['system']['icon'];
        }
        return z_root() . '/images/' . PLATFORM_NAME . '-64.png';
    }

    public static function get_project_favicon()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && array_key_exists('favicon', App::$config['system'])) {
            return App::$config['system']['favicon'];
        }
        return z_root() . '/images/' . PLATFORM_NAME . '.ico';
    }


    public static function get_project_version()
    {
        if (array_path_exists('system/hide_version', App::$config) && intval(App::$config['system']['hide_version'])) {
            return '';
        }
        if (is_array(App::$config) && is_array(App::$config['system']) && array_key_exists('std_version', App::$config['system'])) {
            return App::$config['system']['std_version'];
        }

        return self::get_std_version();
    }

    public static function get_update_version()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['hide_version']) {
            return EMPTY_STR;
        }
        return DB_UPDATE_VERSION;
    }


    public static function get_notify_icon()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['email_notify_icon_url']) {
            return App::$config['system']['email_notify_icon_url'];
        }
        return self::get_project_icon();
    }

    public static function get_site_icon()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && isset(App::$config['system']['site_icon_url']) && App::$config['system']['site_icon_url']) {
            return App::$config['system']['site_icon_url'];
        }
        return self::get_project_icon();
    }

    public static function get_site_favicon()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['site_favicon_url']) {
            return App::$config['system']['site_favicon_url'];
        }
        return self::get_project_favicon();
    }

    public static function get_project_link()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['project_link']) {
            return App::$config['system']['project_link'];
        }
        return 'https://zotlabs.com/' . PLATFORM_NAME;
    }

    public static function get_project_srclink()
    {
        if (is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['project_srclink']) {
            return App::$config['system']['project_srclink'];
        }
        if (PLATFORM_NAME === 'streams') {
            return 'https://codeberg.org/streams/' . PLATFORM_NAME;
		}

        return 'https://codeberg.org/zot/' . PLATFORM_NAME;
    }

    public static function ebs()
    {
        if (defined('EBSSTATE')) {
            return EBSSTATE;
        }
        return 'armed';
    }

    public static function get_zot_revision()
    {
        $x = [ 'revision' => ZOT_REVISION ];
        call_hooks('zot_revision', $x);
        return $x['revision'];
    }

    public static function get_std_version()
    {
        if (defined('STD_VERSION')) {
            return STD_VERSION;
        }
        return '0.0.0';
    }

    public static function compatible_project($p)
    {

        if (in_array(strtolower($p), ['hubzilla', 'zap', 'red', 'misty', 'mistpark', 'redmatrix', 'osada', 'roadhouse','streams'])) {
            return true;
        }
        return false;
    }
}
