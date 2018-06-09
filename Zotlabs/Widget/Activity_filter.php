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
				'title' => t('Show posts that mention or involve me')
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
				'title' => t('Show posts that I have starred')
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
						'title' => sprintf(t('Show posts related to the %s privacy group'), $g['gname'])
					];
				}
				$tabs[] = [
					'id' => 'privacy_groups',
					'label' => t('Privacy Groups'),
					'icon' => 'users',
					'url' => '#',
					'sel' => (($filter_active == 'group') ? true : false),
					'title' => t('Show my privacy groups'),
					'sub' => $gsub

				];
			}
		}

		if(feature_enabled(local_channel(),'forums_tab')) {
			$forums = get_forum_channels(local_channel());

			if($forums) {
				foreach($forums as $f) {
					if(x($_GET,'pf') && x($_GET,'cid')) {
						$forum_active = ((x($_GET,'pf') && $_GET['cid'] == $f['abook_id']) ? 'active' : '');
						$filter_active = 'forums';
					}
					$fsub[] = [
						'label' => $f['xchan_name'],
						'img' => $f['xchan_photo_s'],
						'url' => (($f['private_forum']) ? $f['xchan_url'] : z_root() . '/' . $cmd . '/?f=&pf=1&cid=' . $f['abook_id']),
						'sel' => $forum_active,
						'title' => t('Show posts to this forum'),
						'lock' => (($f['private_forum']) ? 'lock' : '')
					];
				}

				$tabs[] = [
					'id' => 'forums',
					'label' => t('Forums'),
					'icon' => 'comments-o',
					'url' => '#',
					'sel' => (($filter_active == 'forums') ? true : false),
					'title' => t('Show forums'),
					'sub' => $fsub

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
						'title' => sprintf(t('Show posts that I have filed to %s'), $t['term']),
					];
				}

				$tabs[] = [
					'id' => 'saved_folders',
					'label' => t('Saved Folders'),
					'icon' => 'folder',
					'url' => '#',
					'sel' => (($filter_active == 'file') ? true : false),
					'title' => t('Show filed post categories'),
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
				'title' => t('Panel search')
			];
		}

		$name = [];
		if(feature_enabled(local_channel(),'name_tab')) {
			if(x($_GET,'cid') && ! x($_GET,'pf')) {
				$filter_active = 'name';
			}
			$name = [
				'label' => x($_GET,'name') ? $_GET['name'] : t('Filter by name'),
				'icon' => 'filter',
				'url'=> z_root() . '/' . $cmd . '/',
				'sel'=> $filter_active == 'name' ? 'is-valid' : '',
				'title' => ''
			];
		}

		$reset = [];
		if($filter_active) {
			$reset = [
				'label' => '',
				'icon' => 'remove',
				'url'=> z_root() . '/' . $cmd,
				'sel'=> '',
				'title' => t('Remove active filter')
			];
		}

		$arr = ['tabs' => $tabs];

		call_hooks('network_tabs', $arr);

		$o = '';

		if($arr['tabs']) {
			$content =  replace_macros(get_markup_template('common_pills.tpl'), [
				'$pills' => $arr['tabs']
			]);

			$o .= replace_macros(get_markup_template('activity_filter_widget.tpl'), [
				'$title' => t('Activity Filters'),
				'$reset' => $reset,
				'$content' => $content,
				'$name' => $name
			]);
		}

		return $o;

	}

}
