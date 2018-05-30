<?php

namespace Zotlabs\Widget;

class Activity_filter {

	function widget($arr) {

		if(! local_channel())
			return '';

		$starred_active = '';
		$conv_active = '';

		if(x($_GET,'star')) {
			$starred_active = 'active';
		}

		if(x($_GET,'conv')) {
			$conv_active = 'active';
		}

		$cmd = \App::$cmd;

		// tabs
		$tabs = [];

		if(feature_enabled(local_channel(),'personal_tab')) {
			$tabs[] = array(
				'label' => t('Personal Posts'),
				'icon' => 'user-circle',
				'url' => z_root() . '/' . $cmd . '?f=' . ((x($_GET,'order')) ? '&order=' . $_GET['order'] : '') . '&conv=1',
				'sel' => $conv_active,
				'title' => t('Posts that mention or involve you'),
			);
		}

		if(feature_enabled(local_channel(),'star_posts')) {
			$tabs[] = array(
				'label' => t('Starred Posts'),
				'icon' => 'star',
				'url'=>z_root() . '/' . $cmd . '/?f=' . ((x($_GET,'order')) ? '&order=' . $_GET['order'] : '') . '&star=1',
				'sel'=>$starred_active,
				'title' => t('Favourite Posts'),
			);
		}


		$arr = ['tabs' => $tabs];

		call_hooks('network_tabs', $arr);

		$tpl = get_markup_template('common_pills.tpl');

		if($arr['tabs']) {
			return replace_macros($tpl, [
				'$title' => t('Additional Filters'),
				'$tabs' => $arr['tabs'],
			]);
		}
		else {
			return '';
		}
	}

}
