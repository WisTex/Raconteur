<?php

namespace Zotlabs\Access;

use Zotlabs\Lib\PConfig;

/**
 * @brief Permission limits.
 *
 * Permission limits are a very high level permission setting. They are hard
 * limits by design.
 * "Who can view my photos (at all)?"
 * "Who can post photos in my albums (at all)?"
 *
 * For viewing permissions we generally set these to 'anybody' and for write
 * permissions we generally set them to 'those I allow', though many people
 * restrict the viewing permissions further for things like 'Can view my connections'.
 *
 * People get confused enough by permissions that we wanted a place to set their
 * privacy expectations once and be done with it.
 *
 * Connection related permissions like "Can Joe view my photos?" are handled by
 * @ref ::Zotlabs::Lib::Permcat "Permcat" and inherit from the channel's Permission
 * limits.
 *
 * @see Permissions
 */
class PermissionLimits {

	/**
	 * @brief Get standard permission limits.
	 *
	 * Viewing permissions and post_comments permission are set to 'anybody',
	 * other permissions are set to 'those I allow'.
	 *
	 * The list of permissions comes from Permissions::Perms().
	 *
	 * @return array
	 */
	static public function Std_Limits() {
		$limits = [];
		$perms = Permissions::Perms();

		foreach($perms as $k => $v) {
			if(strstr($k, 'view'))
				$limits[$k] = PERMS_PUBLIC;
			else
				$limits[$k] = PERMS_SPECIFIC;
		}

		return $limits;
	}

	/**
	 * @brief Sets a permission limit for a channel.
	 *
	 * @param int $channel_id
	 * @param string $perm
	 * @param int $perm_limit one of PERMS_* constants
	 */
	static public function Set($channel_id, $perm, $perm_limit) {
		PConfig::Set($channel_id, 'perm_limits', $perm, $perm_limit);
	}

	/**
	 * @brief Get a channel's permission limits.
	 *
	 * Return a channel's permission limits from PConfig. If $perm is set just
	 * return this permission limit, if not set, return an array with all
	 * permission limits.
	 *
	 * @param int $channel_id
	 * @param string $perm (optional)
	 * @return
	 *   * \b false if no perm_limits set for this channel
	 *   * \b int if $perm is set, return one of PERMS_* constants for this permission, default 0
	 *   * \b array with all permission limits, if $perm is not set
	 */
	static public function Get($channel_id, $perm = '') {
		if($perm) {
			return intval(PConfig::Get($channel_id, 'perm_limits', $perm));
		}

		PConfig::Load($channel_id);
		if(array_key_exists($channel_id, \App::$config)
				&& array_key_exists('perm_limits', \App::$config[$channel_id]))
			return \App::$config[$channel_id]['perm_limits'];

		return false;
	}
}