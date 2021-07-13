<?php

namespace Zotlabs\Access;

/**
 * @brief PermissionRoles class.
 *
 * @see Permissions
 */
class PermissionRoles {

	/**
	 * @brief PermissionRoles version.
	 *
	 * This must match the version in Permissions.php before permission updates can run.
	 *
	 * @return number
	 */
	static public function version() {
		return 3;
	}

	static function role_perms($role) {

		$ret = [];

		$ret['role'] = $role;

		switch($role) {
			case 'social':
				$ret['perms_auto'] = false;
				$ret['default_collection'] = false;
				$ret['directory_publish'] = true;
				$ret['online'] = true;
				$ret['perms_connect'] = [
					'view_stream', 'view_profile', 'view_contacts', 'view_storage',
					'view_pages', 'send_stream', 'post_mail', 'post_wall', 'post_comments'
				];
				$ret['limits'] = PermissionLimits::Std_Limits();
				break;

			case 'social_restricted':
				$ret['perms_auto'] = false;
				$ret['default_collection'] = true;
				$ret['directory_publish'] = true;
				$ret['online'] = false;
				$ret['perms_connect'] = [
					'view_stream', 'view_profile', 'view_storage',
					'view_pages', 'send_stream', 'post_mail', 'post_wall', 'post_comments'
				];
				$ret['limits'] = PermissionLimits::Std_Limits();
				$ret['limits']['view_contacts'] = PERMS_SPECIFIC;
				break;

			case 'forum':
				$ret['perms_auto'] = true;
				$ret['default_collection'] = false;
				$ret['directory_publish'] = true;
				$ret['online'] = false;
				$ret['perms_connect'] = [
					'view_stream', 'view_profile', 'view_contacts', 'view_storage', 'write_storage',
					'view_pages', 'post_mail', 'post_wall', 'post_comments'
				];
				$ret['limits'] = PermissionLimits::Std_Limits();
				$ret['channel_type'] = 'group';
	
				break;


			case 'forum_moderated':
				$ret['perms_auto'] = true;
				$ret['default_collection'] = false;
				$ret['directory_publish'] = true;
				$ret['online'] = false;
				$ret['perms_connect'] = [
					'view_stream', 'view_profile', 'view_contacts', 'view_storage',
					'view_pages', 'post_mail', 'post_wall', 'post_comments', 'moderated'
				];
				$ret['limits'] = PermissionLimits::Std_Limits();
				$ret['channel_type'] = 'group';
	
				break;

			case 'forum_restricted':
				$ret['perms_auto'] = false;
				$ret['default_collection'] = true;
				$ret['directory_publish'] = true;
				$ret['online'] = false;
				$ret['perms_connect'] = [
					'view_stream', 'view_profile', 'view_contacts', 'view_storage', 'write_storage',
					'view_pages', 'post_mail', 'post_wall', 'post_comments'
				];
				$ret['limits'] = PermissionLimits::Std_Limits();
				$ret['limits']['view_contacts'] = PERMS_SPECIFIC;
				$ret['channel_type'] = 'group';
				break;


//			case 'collection':
//				$ret['perms_auto'] = true;
//				$ret['default_collection'] = false;
//				$ret['directory_publish'] = true;
//				$ret['online'] = false;
//				$ret['perms_connect'] = [
//					'view_stream', 'view_profile', 'view_contacts', 'view_storage',
//					'view_pages', 'post_mail', 'post_comments'
//				];
//				$ret['limits'] = PermissionLimits::Std_Limits();
//				$ret['channel_type'] = 'collection';
//	
//				break;
//
//			case 'collection_restricted':
//				$ret['perms_auto'] = false;
//				$ret['default_collection'] = true;
//				$ret['directory_publish'] = true;
//				$ret['online'] = false;
//				$ret['perms_connect'] = [
//					'view_stream', 'view_profile', 'view_storage',
//					'view_pages', 'post_mail', 'post_comments'
//				];
//				$ret['limits'] = PermissionLimits::Std_Limits();
//				$ret['limits']['view_contacts'] = PERMS_SPECIFIC;
//				$ret['channel_type'] = 'collection';
//				break;
//
//			case 'feed':
//				$ret['perms_auto'] = true;
//				$ret['default_collection'] = false;
//				$ret['directory_publish'] = true;
//				$ret['online'] = false;
//				$ret['perms_connect'] = [
//					'view_stream', 'view_profile', 'view_contacts', 'view_storage',
//					'view_pages', 'send_stream', 'post_wall', 'post_mail', 'post_comments',
//					'republish'
//				];
//				$ret['limits'] = PermissionLimits::Std_Limits();
//
//				break;
//
//			case 'repository':
//				//Legacy settings to cover all channel_types previously in Libzot.php
//				$ret['channel_type'] = 'group';
			default:
				break;
		}


		$x = get_config('system','role_perms');
		// let system settings over-ride any or all
		if($x && is_array($x) && array_key_exists($role,$x))
			$ret = array_merge($ret,$x[$role]);

		/**
		 * @hooks get_role_perms
		 *   * \e array
		 */
		$x = [ 'role' => $role, 'result' => $ret ];

		call_hooks('get_role_perms', $x);

		return $x['result'];
	}


	/**
	 * @brief Array with translated role names and grouping.
	 *
	 * Return an associative array with grouped role names that can be used
	 * to create select groups like in \e field_select_grouped.tpl.
	 *
	 * @return array
	 */
	static public function roles() {
		$roles = [
			t('Social Networking') => [
				'social' => t('Social - Normal'),
				'social_restricted' => t('Social - Restricted')
			],

			t('Community Group') => [
				'forum' => t('Group - Normal'),
				'forum_restricted' => t('Group - Restricted'),
				'forum_moderated' => t('Group - Moderated')
			],

//			t('Collection') => [
//				'collection' => t('Collection - Normal'),
//				'collection_restricted' => t('Collection - Restricted')
//			]

		];

		call_hooks('list_permission_roles',$roles);

		return $roles;
	}

}
