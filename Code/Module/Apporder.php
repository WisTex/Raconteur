<?php

namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\Apps;
use Code\Lib\Navbar;
use Code\Render\Theme;


class Apporder extends Controller
{
    public function get()
    {

        if (!local_channel()) {
            return '';
        }
    
        Navbar::set_selected('Order Apps');

        $nav_apps = [];
        $navbar_apps = [];
    
        foreach (['nav_featured_app', 'nav_pinned_app'] as $l) {
            $syslist = [];
            $list = Apps::app_list(local_channel(), false, [$l]);
            if ($list) {
                foreach ($list as $li) {
                    $syslist[] = Apps::app_encode($li);
                }
            }

            Apps::translate_system_apps($syslist);

            usort($syslist, 'Code\\Lib\\Apps::app_name_compare');

            $syslist = Apps::app_order(local_channel(), $syslist, $l);

            foreach ($syslist as $app) {
                if ($l === 'nav_pinned_app') {
                    $navbar_apps[] = Apps::app_render($app, 'nav-order-pinned');
                }
                else {
                    $nav_apps[] = Apps::app_render($app, 'nav-order');
                }
            }
        }

        return replace_macros(Theme::get_template('apporder.tpl'), [
            '$arrange'     => t('Arrange Apps'),
            '$header' => [t('Change order of pinned navbar apps'), t('Change order of app tray apps')],
            '$desc' => [t('Use arrows to move the corresponding app left (top) or right (bottom) in the navbar'),
                t('Use arrows to move the corresponding app up or down in the app tray')],
            '$nav_apps' => $nav_apps,
            '$navbar_apps' => $navbar_apps
        ]);
    }
}
