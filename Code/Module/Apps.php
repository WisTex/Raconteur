<?php

namespace Code\Module;

use App;
use Code\Lib as Zlib;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Lib\Navbar;
use Code\Render\Theme;


class Apps extends Controller
{

    public function get()
    {

        Navbar::set_selected('Apps');

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
            $list = Zlib\Apps::app_list(($available || Channel::is_system(local_channel()) ? 0 : local_channel()), (($mode == 'edit') ? true : false), $cat);
            if ($list) {
                foreach ($list as $x) {
                    $syslist[] = Zlib\Apps::app_encode($x);
                }
            }
            Zlib\Apps::translate_system_apps($syslist);
        } else {
            $syslist = Zlib\Apps::get_system_apps(true);
        }

        usort($syslist, 'Code\\Lib\\Apps::app_name_compare');

        //  logger('apps: ' . print_r($syslist,true));

        foreach ($syslist as $app) {
            $apps[] = Zlib\Apps::app_render($app, (($available) ? 'install' : $mode));
        }
		
        return replace_macros(Theme::get_template('myapps.tpl'), array(
            '$sitename' => get_config('system', 'sitename'),
            '$cat' => $cat,
            '$title' => (($available) ? t('Available Apps') : t('Installed Apps')),
            '$apps' => $apps,
            '$authed' => ((local_channel()) ? true : false),
            '$manage' => (($available) ? EMPTY_STR : t('Manage apps')),
            '$create' => (($mode == 'edit') ? t('Create Custom App') : '')
        ));
    }
}
