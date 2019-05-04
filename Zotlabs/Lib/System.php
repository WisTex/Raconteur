<?php

namespace Zotlabs\Lib;

use App;

class System {

	static public function get_platform_name() {
		if(is_array(App::$config) && is_array(App::$config['system']) && array_key_exists('platform_name',App::$config['system']))
			return App::$config['system']['platform_name'];
		return PLATFORM_NAME;
	}

	static public function get_site_name() {
		if(is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['sitename'])
			return App::$config['system']['sitename'];
		return '';
	}

	static public function get_banner() {

		if(is_array(App::$config) && is_array(App::$config['system']) && array_key_exists('banner',App::$config['system']))
			return App::$config['system']['banner'];


		return EMPTY_STR;
	}

	static public function get_project_icon() {

		return z_root() . '/images/zap4-64.png';

		if(defined('NOMADIC')) {
			return '&#x26A1;';
		}
		else {
			return '&#x2638;';
		}
	}


	static public function get_project_version() {
		if(is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['hide_version'])
			return '';
		if(is_array(App::$config) && is_array(App::$config['system']) && array_key_exists('std_version',App::$config['system']))
			return App::$config['system']['std_version'];

		return self::get_std_version();
	}

	static public function get_update_version() {
		if(is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['hide_version'])
			return '';
		return DB_UPDATE_VERSION;
	}


	static public function get_notify_icon() {
		if(is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['email_notify_icon_url'])
			return App::$config['system']['email_notify_icon_url'];
		return z_root() . DEFAULT_NOTIFY_ICON;
	}

	static public function get_site_icon() {
		if(is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['site_icon_url'])
			return App::$config['system']['site_icon_url'];
		return z_root() . DEFAULT_PLATFORM_ICON ;
	}


	static public function get_project_link() {
		if(is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['project_link'])
			return App::$config['system']['project_link'];
		return 'https://framagit.org/zot/zap';
	}

	static public function get_project_srclink() {
		if(is_array(App::$config) && is_array(App::$config['system']) && App::$config['system']['project_srclink'])
			return App::$config['system']['project_srclink'];
		return 'https://framagit.org/zot/zap';
	}

	static public function get_server_role() {
		return 'pro';
	}

	static public function ebs() {
		if(defined('EBSSTATE')) {
			return EBSSTATE;
		}
		return 'armed';
	}

	static public function get_zot_revision() {
		$x = [ 'revision' => ZOT_REVISION ]; 
		call_hooks('zot_revision',$x);
		return $x['revision'];
	}

	static public function get_std_version() {
		if(defined('STD_VERSION'))
			return STD_VERSION;
		return '0.0.0';
	}

	static public function compatible_project($p) {

		if(get_directory_realm() != DIRECTORY_REALM)
			return true;
		if(in_array(strtolower($p),['hubzilla','zap','red','osada']))
			return true;
		return false;
	}
}
