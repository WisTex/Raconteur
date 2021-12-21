<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib as Zlib;
use Zotlabs\Web\Controller;

class Apps extends Controller
{

    public function get()
    {

        nav_set_selected('Apps');

        if (argc() == 2 && argv(1) == 'edit') {
            $mode = 'edit';
        } else {
            $mode = 'list';
        }

        $available = ((argc() == 2 && argv(1) === 'available') ? true : false);

        $_SESSION['return_url'] = App::$query_string;

        $apps = [];

        if (local_channel()) {
            Zlib\Apps::import_system_apps();
            $syslist = [];
            $cat = ((array_key_exists('cat', $_GET) && $_GET['cat']) ? [escape_tags($_GET['cat'])] : '');
            $list = Zlib\Apps::app_list((($available) ? 0 : local_channel()), (($mode == 'edit') ? true : false), $cat);
            if ($list) {
                foreach ($list as $x) {
                    $syslist[] = Zlib\Apps::app_encode($x);
                }
            }
            Zlib\Apps::translate_system_apps($syslist);
        } else {
            $syslist = Zlib\Apps::get_system_apps(true);
        }

        usort($syslist, 'Zotlabs\\Lib\\Apps::app_name_compare');

        //  logger('apps: ' . print_r($syslist,true));

        foreach ($syslist as $app) {
            $apps[] = Zlib\Apps::app_render($app, (($available) ? 'install' : $mode));
        }
		
		// @TODO not ready for prime time. The manage url redirects to installed apps
		// and we need to edit available apps
		$sys_edit = false; // (local_channel() && is_sys_channel(local_channel()));

        return replace_macros(get_markup_template('myapps.tpl'), array(
            '$sitename' => get_config('system', 'sitename'),
            '$cat' => $cat,
            '$title' => (($available) ? t('Available Apps') : t('Installed Apps')),
            '$apps' => $apps,
            '$authed' => ((local_channel()) ? true : false),
            '$manage' => (($available && ! $sys_edit) ? EMPTY_STR : t('Manage apps')),
            '$create' => (($mode == 'edit') ? t('Create Custom App') : '')
        ));
    }
}
