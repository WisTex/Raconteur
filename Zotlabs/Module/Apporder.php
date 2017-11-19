<?php

namespace Zotlabs\Module;

use \Zotlabs\Lib as Zlib;

class Apporder extends \Zotlabs\Web\Controller {

	function post() {

	}

	function get() {

		if(! local_channel())
			return;

		nav_set_selected('Order Apps');

		$syslist = array();
		$list = Zlib\Apps::app_list(local_channel(), false, ['nav_featured_app', 'nav_pinned_app']);
		if($list) {
			foreach($list as $li) {
				$syslist[] = Zlib\Apps::app_encode($li);
			}
		}
		Zlib\Apps::translate_system_apps($syslist);

		usort($syslist,'Zotlabs\\Lib\\Apps::app_name_compare');

		$syslist = Zlib\Apps::app_order(local_channel(),$syslist);

		foreach($syslist as $app) {
			if(strpos($app['categories'],'nav_pinned_app') !== false) {
				$navbar_apps[] = Zlib\Apps::app_render($app,'nav-order');
			}
			else {
				$nav_apps[] = Zlib\Apps::app_render($app,'nav-order');
			}
		}

		return replace_macros(get_markup_template('apporder.tpl'),
			[
				'$header' => [t('Change Order of Pinned Navbar Apps'), t('Change Order of App Tray Apps')],
				'$desc' => [t('Use arrows to move the corresponding app left (top) or right (bottom) in the navbar'), t('Use arrows to move the corresponding app up or down in the app tray')],
				'$nav_apps' => $nav_apps,
				'$navbar_apps' => $navbar_apps
			]
		);
	}
}
