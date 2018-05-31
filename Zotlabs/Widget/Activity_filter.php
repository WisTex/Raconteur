<?php

namespace Zotlabs\Widget;

class Activity_filter {

	function widget($arr) {

		if(! local_channel())
			return '';

		$cmd = \App::$cmd;
		$filter_active = false;

		$tabs = [];

		if(feature_enabled(local_channel(),'personal_tab')) {
			if(x($_GET,'conv')) {
				$conv_active = (($_GET['conv'] == 1) ? 'active' : '');
				$filter_active = true;
			}

			$tabs[] = [
				'label' => t('Personal Posts'),
				'icon' => 'user-circle',
				'url' => z_root() . '/' . $cmd . '/?f=&conv=1',
				'sel' => $conv_active,
				'title' => t('Show posts that mention or involve me'),
			];
		}

		if(feature_enabled(local_channel(),'star_posts')) {
			if(x($_GET,'star')) {
				$starred_active = (($_GET['star'] == 1) ? 'active' : '');
				$filter_active = true;
			}

			$tabs[] = [
				'label' => t('Starred Posts'),
				'icon' => 'star',
				'url'=>z_root() . '/' . $cmd . '/?f=&star=1',
				'sel'=>$starred_active,
				'title' => t('Show posts that i have starred'),
			];
		}

		if(feature_enabled(local_channel(),'groups')) {
			$groups = q("SELECT * FROM groups WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
				intval(local_channel())
			);

			if($groups) {
				foreach($groups as $g) {
					if(x($_GET,'gid')) {
						$group_active = (($_GET['gid'] == $g['id']) ? 'active' : '');
						$filter_active = true;
					}

					$tabs[] = [
						'label' => $g['gname'],
						'icon' => 'users',
						'url' => z_root() . '/' . $cmd . '/?f=&gid=' . $g['id'],
						'sel' => $group_active,
						'title' => sprintf(t('Show posts related to the %s privacy group'), $g['gname']),
					];
				}
			}
		}

		if(feature_enabled(local_channel(),'filing')) {
			$terms = q("select distinct term from term where uid = %d and ttype = %d order by term asc",
				intval(local_channel()),
				intval(TERM_FILE)
			);

			if($terms) {
				foreach($terms as $t) {
					if(x($_GET,'file')) {
						$file_active = (($_GET['file'] == $t['term']) ? 'active' : '');
						$filter_active = true;
					}

					$tabs[] = [
						'label' => $t['term'],
						'icon' => 'folder',
						'url' => z_root() . '/' . $cmd . '/?f=&file=' . $t['term'],
						'sel' => $file_active,
						'title' => sprintf(t('Show posts that i have filed to %s'), $t['term']),
					];
				}
			}
		}

		if(x($_GET,'search')) {
			$filter_active = true;
		}

		if($filter_active) {
			$reset = [
				'label' => t('Remove Filter'),
				'icon' => 'remove',
				'url'=> z_root() . '/' . $cmd,
				'sel'=> 'active bg-danger',
				'title' => t('Remove active filter'),
			];
			array_unshift($tabs, $reset);
		}

		$arr = ['tabs' => $tabs];

		call_hooks('network_tabs', $arr);

		$tpl = get_markup_template('common_pills.tpl');

		if($arr['tabs']) {
			return replace_macros($tpl, [
				'$title' => t('Activity Filters'),
				'$tabs' => $arr['tabs'],
			]);
		}
		else {
			return '';
		}
	}

}
