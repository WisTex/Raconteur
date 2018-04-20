<?php

namespace Zotlabs\Update;

class _1212 {

	function run() {

		$r = q("select channel_id from channel where true");
		if($r) {
			foreach($r as $rv) {
				$role = get_pconfig($rv['channel_id'],'system','permissions_role');
				if($role !== 'custom') {
					$role_permissions = \Zotlabs\Access\PermissionRoles::role_perms($role);
					if(array_key_exists('limits',$role_permissions) && array_key_exists('post_comments',$role_permissions['limits'])) {
						set_pconfig($rv['channel_id'],'perm_limits','post_comments',$role_permissions['limits']['post_comments']);
					}
				}
			}
		}

		return UPDATE_SUCCESS;

	}

}
