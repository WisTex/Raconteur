<?php
namespace Zotlabs\Module;

require_once('include/group.php');



class Group extends \Zotlabs\Web\Controller {

	function init() {
		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		\App::$profile_uid = local_channel();

		nav_set_selected('Privacy Groups');
	}

	function post() {
	
		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}
	
		if((argc() == 2) && (argv(1) === 'new')) {
			check_form_security_token_redirectOnErr('/group/new', 'group_edit');
			
			$name = notags(trim($_POST['groupname']));
			$public = intval($_POST['public']);
			$r = group_add(local_channel(),$name,$public);
			if($r) {
				info( t('Privacy group created.') . EOL );
			}
			else {
				notice( t('Could not create privacy group.') . EOL );
			}
			goaway(z_root() . '/group');
	
		}
		if((argc() == 2) && (intval(argv(1)))) {
			check_form_security_token_redirectOnErr('/group', 'group_edit');
			
			$r = q("SELECT * FROM groups WHERE id = %d AND uid = %d LIMIT 1",
				intval(argv(1)),
				intval(local_channel())
			);
			if(! $r) {
				notice( t('Privacy group not found.') . EOL );
				goaway(z_root() . '/connections');
	
			}
			$group = $r[0];
			$groupname = notags(trim($_POST['groupname']));
			$public = intval($_POST['public']);
	
			if((strlen($groupname))  && (($groupname != $group['gname']) || ($public != $group['visible']))) {
				$r = q("UPDATE groups SET gname = '%s', visible = %d  WHERE uid = %d AND id = %d",
					dbesc($groupname),
					intval($public),
					intval(local_channel()),
					intval($group['id'])
				);
				if($r)
					info( t('Privacy group updated.') . EOL );
				build_sync_packet(local_channel(),null,true);
			}
	
			goaway(z_root() . '/group/' . argv(1) . '/' . argv(2));
		}
		return;	
	}
	
	function get() {

		$change = false;
	
		logger('mod_group: ' . \App::$cmd,LOGGER_DEBUG);
		
		if(! local_channel()) {
			notice( t('Permission denied') . EOL);
			return;
		}

		// Switch to text mode interface if we have more than 'n' contacts or group members
		$switchtotext = get_pconfig(local_channel(),'system','groupedit_image_limit');
		if($switchtotext === false)
			$switchtotext = get_config('system','groupedit_image_limit');
		if($switchtotext === false)
			$switchtotext = 400;


		if((argc() == 1) || ((argc() == 2) && (argv(1) === 'new'))) {

			$new = (((argc() == 2) && (argv(1) === 'new')) ? true : false);

			$groups = q("SELECT id, gname FROM groups WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
				intval(local_channel())
			);

			$i = 0;
			foreach($groups as $group) {
				$entries[$i]['name'] = $group['gname'];
				$entries[$i]['id'] = $group['id'];
				$entries[$i]['count'] = count(group_get_members($group['id']));
				$i++;
			}

			$tpl = get_markup_template('privacy_groups.tpl');
			$o = replace_macros($tpl, [
				'$title' => t('Privacy Groups'),
				'$add_new_label' => t('Add Group'),
				'$new' => $new,

				// new group form
				'$gname' => array('groupname',t('Privacy group name')),
				'$public' => array('public',t('Members are visible to other channels'), false),
				'$form_security_token' => get_form_security_token("group_edit"),
				'$submit' => t('Submit'),

				// groups list
				'$title' => t('Privacy Groups'),
				'$name_label' => t('Name'),
				'$count_label' => t('Members'),
				'$entries' => $entries
			]);

			return $o;

		}




		$context = array('$submit' => t('Submit'));
		$tpl = get_markup_template('group_edit.tpl');
	
		if((argc() == 3) && (argv(1) === 'drop')) {
			check_form_security_token_redirectOnErr('/group', 'group_drop', 't');
			
			if(intval(argv(2))) {
				$r = q("SELECT gname FROM groups WHERE id = %d AND uid = %d LIMIT 1",
					intval(argv(2)),
					intval(local_channel())
				);
				if($r) 
					$result = group_rmv(local_channel(),$r[0]['gname']);
				if($result)
					info( t('Privacy group removed.') . EOL);
				else
					notice( t('Unable to remove privacy group.') . EOL);
			}
			goaway(z_root() . '/group');
			// NOTREACHED
		}
	
	
		if((argc() > 2) && intval(argv(1)) && argv(2)) {
	
			check_form_security_token_ForbiddenOnErr('group_member_change', 't');
	
			$r = q("SELECT abook_xchan from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d and xchan_deleted = 0 and abook_self = 0 and abook_blocked = 0 and abook_pending = 0 limit 1",
				dbesc(base64url_decode(argv(2))),
				intval(local_channel())
			);
			if(count($r))
				$change = base64url_decode(argv(2));
	
		}
	
		if((argc() > 1) && (intval(argv(1)))) {
	
			require_once('include/acl_selectors.php');
			$r = q("SELECT * FROM groups WHERE id = %d AND uid = %d AND deleted = 0 LIMIT 1",
				intval(argv(1)),
				intval(local_channel())
			);
			if(! $r) {
				notice( t('Privacy group not found.') . EOL );
				goaway(z_root() . '/connections');
			}
			$group = $r[0];
	
	
			$members = group_get_members($group['id']);
	
			$preselected = array();
			if(count($members))	{
				foreach($members as $member)
					if(! in_array($member['xchan_hash'],$preselected))
						$preselected[] = $member['xchan_hash'];
			}
	
			if($change) {
	
				if(in_array($change,$preselected)) {
					group_rmv_member(local_channel(),$group['gname'],$change);
				}
				else {
					group_add_member(local_channel(),$group['gname'],$change);
				}
	
				$members = group_get_members($group['id']);
	
				$preselected = array();
				if(count($members))	{
					foreach($members as $member)
						$preselected[] = $member['xchan_hash'];
				}
			}

			$context = $context + array(
				'$title' => sprintf(t('Privacy Group: %s'), $group['gname']),
				'$details_label' => t('Edit'),
				'$gname' => array('groupname',t('Privacy group name: '),$group['gname'], ''),
				'$gid' => $group['id'],
				'$drop' => $drop_txt,
				'$public' => array('public',t('Members are visible to other channels'), $group['visible'], ''),
				'$form_security_token_edit' => get_form_security_token('group_edit'),
				'$delete' => t('Delete Group'),
				'$form_security_token_drop' => get_form_security_token("group_drop"),
			);
	
		}
	
		if(! isset($group))
			return;
	
		$groupeditor = array(
			'label_members' => t('Group members'),
			'members' => array(),
			'label_contacts' => t('Not in this group'),
			'contacts' => array(),
		);
			
		$sec_token = addslashes(get_form_security_token('group_member_change'));
		$textmode = (($switchtotext && (count($members) > $switchtotext)) ? true : 'card');
		foreach($members as $member) {
			if($member['xchan_url']) {
				$member['archived'] = (intval($member['abook_archived']) ? true : false);
				$member['click'] = 'groupChangeMember(' . $group['id'] . ',\'' . base64url_encode($member['xchan_hash']) . '\',\'' . $sec_token . '\'); return false;';
				$groupeditor['members'][] = micropro($member,true,'mpgroup', $textmode);
			}
			else
				group_rmv_member(local_channel(),$group['gname'],$member['xchan_hash']);
		}
	
		$r = q("SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash WHERE abook_channel = %d AND abook_self = 0 and abook_blocked = 0 and abook_pending = 0 and xchan_deleted = 0 order by xchan_name asc",
			intval(local_channel())
		);
	
		if(count($r)) {
			$textmode = (($switchtotext && (count($r) > $switchtotext)) ? true : 'card');
			foreach($r as $member) {
				if(! in_array($member['xchan_hash'],$preselected)) {
					$member['archived'] = (intval($member['abook_archived']) ? true : false);
					$member['click'] = 'groupChangeMember(' . $group['id'] . ',\'' . base64url_encode($member['xchan_hash']) . '\',\'' . $sec_token . '\'); return false;';
					$groupeditor['contacts'][] = micropro($member,true,'mpall', $textmode);
				}
			}
		}
	
		$context['$groupeditor'] = $groupeditor;
		$context['$desc'] = t('Click a channel to toggle membership');
	
		if($change) {
			$tpl = get_markup_template('groupeditor.tpl');
			echo replace_macros($tpl, $context);
			killme();
		}
		
		return replace_macros($tpl, $context);
	
	}
	
	
}
