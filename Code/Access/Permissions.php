<?php

namespace Code\Access;

use Code\Lib\Apps;
use Code\Lib\Channel;
use Code\Lib\Permcat;
use Code\Extend\Hook;

/**
 * @brief Extensible permissions.
 *
 * To add new permissions, add to the list of $perms below, with a simple description.
 *
 * Also visit PermissionRoles.php and add to the $ret['perms_connect'] property for any role
 * if this permission should be granted to new connections.
 *
 * Next look at PermissionRoles::new_custom_perms() and provide a handler for updating custom
 * permission roles. You will want to set a default PermissionLimit for each channel and also
 * provide a sane default for any existing connections. You may or may not wish to provide a
 * default auto permission. If in doubt, leave this alone as custom permissions by definition
 * are the responsibility of the channel owner to manage. You just don't want to create any
 * suprises or break things so you have an opportunity to provide sane settings.
 *
 * Update the version here and in PermissionRoles.
 *
 *
 * Permissions with 'view' in the name are considered read permissions. Anything
 * else requires authentication. Read permission limits are PERMS_PUBLIC and anything else
 * is given PERMS_SPECIFIC.
 *
 * PermissionLimits::Std_limits() retrieves the standard limits. A permission role
 * MAY alter an individual setting after retrieving the Std_limits if you require
 * something different for a specific permission within the given role.
 *
 */
class Permissions
{

    /**
     * @brief Permissions version.
     *
     * This must match the version in PermissionRoles.php before permission updates can run.
     *
     * @return int
     */
    public static function version(): int
    {
        return 3;
    }

    /**
     * @brief Return an array with Permissions.
     *
     * @param string $filter (optional) only passed to hook permissions_list
     * @return array Associative array with permissions and short description.
     */
    public static function Perms($filter = ''): array
    {

        $perms = [
            'view_stream'    => t('Grant viewing access to your channel stream and posts'),
            'deliver_stream' => t('Permit delivery of your channel stream and posts'),
            'view_profile'   => t('Grant viewing access to your default channel profile'),
            'view_contacts'  => t('Grant viewing access to your address book (connections)'),
            'view_storage'   => t('Grant viewing access to your file storage and photos'),
            'post_wall'      => t('Grant permission to post on your channel (wall) page'),
            'post_mail'      => t('Accept delivery of direct messages and personal mail'),
            'send_stream'    => t('Accept delivery of their posts and all comments to their posts'),
            'post_comments'  => t('Accept delivery of their comments and likes on your posts'),
            'write_storage'  => t('Grant upload permissions to your file storage and photos'),
            'republish'      => t('Grant permission to republish/mirror your posts'),
            'moderated'      => t('Accept comments and wall posts only after approval (moderation)'),
            'delegate'       => t('Grant channel administration (delegation) permission')
        ];

        $x = [
            'permissions' => $perms,
            'filter' => $filter
        ];
        /**
         * @hooks permissions_list
         *   * \e array \b permissions
         *   * \e string \b filter
         */
        Hook::call('permissions_list', $x);

        return($x['permissions']);
    }

    /**
     * @brief Perms from the above list that are blocked from anonymous observers.
     *
     * e.g. you must be authenticated.
     *
     * @return array Associative array with permissions and short description.
     */
    public static function BlockedAnonPerms()
    {

        $res = [];
        $perms = PermissionLimits::Std_limits();
        foreach ($perms as $perm => $limit) {
            if ($limit != PERMS_PUBLIC) {
                $res[] = $perm;
            }
        }

        $x = ['permissions' => $res];
        /**
         * @hooks write_perms
         *   * \e array \b permissions
         */
        Hook::call('write_perms', $x);

        return($x['permissions']);
    }

    /**
     * @brief Converts indexed perms array to associative perms array.
     *
     * Converts [ 0 => 'view_stream', ... ]
     * to [ 'view_stream' => 1 ] for any permissions in $arr;
     * Undeclared permissions which exist in Perms() are added and set to 0.
     *
     * @param array $arr
     * @return array
     */
    public static function FilledPerms($arr)
    {
        $everything = self::Perms();
        $ret = [];
        foreach ($everything as $k => $v) {
            if (in_array($k, $arr)) {
                $ret[$k] = 1;
            } else {
                $ret[$k] = 0;
            }
        }
        return $ret;
    }

    /**
     * @brief Convert perms array to indexed array.
     *
     * Converts [ 'view_stream' => 1 ] for any permissions in $arr
     * to [ 0 => ['name' => 'view_stream', 'value' => 1], ... ]
     *
     * @param array $arr associative perms array 'view_stream' => 1
     * @return array Indexed array with elements that look like
     *   * \e string \b name the perm name (e.g. view_stream)
     *   * \e int \b value the value of the perm (e.g. 1)
     */
    public static function OPerms($arr)
    {
        $ret = [];
        if ($arr) {
            foreach ($arr as $k => $v) {
                $ret[] = [ 'name' => $k, 'value' => $v ];
            }
        }
        return $ret;
    }

    /**
     * @brief
     *
     * @param int $channel_id
     * @return bool|array
     */
    public static function FilledAutoperms($channel_id)
    {
        if (! intval(get_pconfig($channel_id, 'system', 'autoperms'))) {
            return false;
        }

        $arr = [];
        $r = q(
            "select * from pconfig where uid = %d and cat = 'autoperms'",
            intval($channel_id)
        );
        if ($r) {
            foreach ($r as $rr) {
                $arr[$rr['k']] = intval($rr['v']);
            }
        }
        return $arr;
    }

    /**
     * @brief Compares that all Permissions from $p1 exist also in $p2.
     *
     * @param array $p1 The perms that have to exist in $p2
     * @param array $p2 The perms to compare against
     * @return bool true if all perms from $p1 exist also in $p2
     */
    public static function PermsCompare($p1, $p2)
    {
        foreach ($p1 as $k => $v) {
            if (! array_key_exists($k, $p2)) {
                return false;
            }

            if ($p2[$k] != $v) {
                return false;
            }
        }

        return true;
    }

    /**
     * @brief
     *
     * @param int $channel_id A channel id
     * @return array Associative array with
     *   * \e array \b perms Permission array
     *   * \e int \b automatic 0 or 1
     */
    public static function connect_perms($channel_id)
    {

        $my_perms = [];
        $permcat = null;
        $automatic = 0;

        // If a default permcat exists, use that

        $pc = ((Apps::system_app_installed($channel_id, 'Roles')) ? get_pconfig($channel_id, 'system', 'default_permcat') : 'default');
        if (! in_array($pc, [ '','default' ])) {
            $pcp = new Permcat($channel_id);
            $permcat = $pcp->fetch($pc);
            if ($permcat && $permcat['perms']) {
                foreach ($permcat['perms'] as $p) {
                    $my_perms[$p['name']] = $p['value'];
                }
            }
        }

        $automatic = intval(get_pconfig($channel_id, 'system', 'autoperms'));

        // look up the permission role to see if it specified auto-connect
        // and if there was no permcat or a default permcat, set the perms
        // from the role

        $role = get_pconfig($channel_id, 'system', 'permissions_role');
        if ($role) {
            $xx = PermissionRoles::role_perms($role);

            if ((! $my_perms) && ($xx['perms_connect'])) {
                $default_perms = $xx['perms_connect'];
                $my_perms = Permissions::FilledPerms($default_perms);
            }
        }

        // If we reached this point without having any permission information,
        // it is likely a custom permissions role. First see if there are any
        // automatic permissions.

        if (! $my_perms) {
            $m = Permissions::FilledAutoperms($channel_id);
            if ($m) {
                $my_perms = $m;
            }
        }

        // If we reached this point with no permissions, the channel is using
        // custom perms but they are not automatic. They will be stored in abconfig with
        // the channel's channel_hash (the 'self' connection).

        if (! $my_perms) {
            $c = Channel::from_id($channel_id);
            if ($c) {
                $my_perms = Permissions::FilledPerms(explode(',', get_abconfig($channel_id, $c['channel_hash'], 'system', 'my_perms', EMPTY_STR)));
            }
        }

        return ( [ 'perms' => $my_perms, 'automatic' => $automatic ] );
    }


    public static function serialise($p)
    {
        $n = [];
        if ($p) {
            foreach ($p as $k => $v) {
                if (intval($v)) {
                    $n[] = $k;
                }
            }
        }
        return implode(',', $n);
    }
}
