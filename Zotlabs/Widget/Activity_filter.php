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
				$filter_active = 'personal';
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
				$filter_active = 'star';
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
						$filter_active = 'group';
					}
					$gsub[] = [
						'label' => $g['gname'],
						'icon' => '',
						'url' => z_root() . '/' . $cmd . '/?f=&gid=' . $g['id'],
						'sel' => $group_active,
						'title' => sprintf(t('Show posts related to the %s privacy group'), $g['gname']),
					];
				}
				$tabs[] = [
					'id' => 'privacy_groups',
					'label' => t('Privacy Groups'),
					'icon' => 'users',
					'url' => '#',
					'sel' => (($filter_active == 'group') ? true : false),
					'title' => sprintf(t('Show posts that i have filed to %s'), $t['term']),
					'sub' => $gsub

				];
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
						$filter_active = 'file';
					}
					$tsub[] = [
						'label' => $t['term'],
						'icon' => '',
						'url' => z_root() . '/' . $cmd . '/?f=&file=' . $t['term'],
						'sel' => $file_active,
						'title' => '',
					];
				}

				$tabs[] = [
					'label' => t('Saved Folders'),
					'icon' => 'folder',
					'url' => '#',
					'sel' => (($filter_active == 'file') ? true : false),
					'title' => sprintf(t('Show posts that I have filed to %s'), $t['term']),
					'sub' => $tsub

				];
			}
		}

		if(x($_GET,'search')) {
			$filter_active = 'search';
			$tabs[] = [
				'label' => t('Search'),
				'icon' => 'search',
				'url' => z_root() . '/' . $cmd . '/?f=&search=' . $_GET['search'],
				'sel' => 'active disabled',
				'title' => t('Panel search'),
			];
		}

		$reset = [];
		if($filter_active) {
			$reset = [
				'label' => '',
				'icon' => 'remove',
				'url'=> z_root() . '/' . $cmd,
				'sel'=> '',
				'title' => t('Remove active filter'),
			];
		}

		$arr = ['tabs' => $tabs];

		call_hooks('network_tabs', $arr);

		$o = '';

		if($arr['tabs']) {
			$content =  replace_macros(get_markup_template('common_pills.tpl'), [
				'$pills' => $arr['tabs'],
			]);

			$o .= replace_macros(get_markup_template('activity_filter_widget.tpl'), [
				'$title' => t('Activity Filters'),
				'$reset' => $reset,
				'$content' => $content,
			]);
		}

		return $o;

	}

}
