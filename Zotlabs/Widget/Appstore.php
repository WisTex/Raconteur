<?php

namespace Zotlabs\Widget;


class Appstore {

	function widget($arr) {
		$store = ((argc() > 1 && argv(1) === 'available') ? 1 : 0);
		return replace_macros(get_markup_template('appstore.tpl'), [ 
			'$title' => t('App Collections'),
			'$options' => [
				[ z_root() . '/apps',           t('Installed Apps'), 1 - $store ],
				[ z_root() . '/apps/available', t('Available Apps'), $store ]
			]
		]);
	}
}
