<?php

namespace Code\Lib;

use Code\Access\PermissionRoles;
use Code\Access\Permissions;
use Code\Lib\Channel;
use Code\Extend\Hook;

/**
 * @brief Permission Categories. Permission rules for various classes of connections.
 *
 * Connection permissions answer the question "Can Joe view my photos?"
 *
 * Some permissions may be inherited from the channel's "privacy settings"
 * (@ref ::Code::Access::PermissionLimits "PermissionLimits") "Who can view my
 * photos (at all)?" which have higher priority than individual connection settings.
 * We evaluate permission limits first, and then fall through to connection
 * permissions if the permission limits didn't already make a definitive decision.
 *
 * After PermissionLimits and connection permissions are evaluated, individual
 * content ACLs are evaluated (@ref ::Code::Access::AccessList "AccessList").
 * These answer the question "Can Joe view *this* album/photo?".
 */

class Permcat
{

    /**
     * @var array
     */
    private $permcats = [];

    /**
     * @brief Permcat constructor.
     *
     * @param int $channel_id
     */
    public function __construct($channel_id, $abook_id = 0)
    {

        $perms = [];

        // first check role perms for a perms_connect setting

        $role = get_pconfig($channel_id, 'system', 'permissions_role');
        if ($role) {
            $x = PermissionRoles::role_perms($role);
            if ($x['perms_connect']) {
                $perms = Permissions::FilledPerms($x['perms_connect']);
            }
        }

        // if no role perms it may be a custom role, see if there any autoperms

        if (! $perms) {
            $perms = Permissions::FilledAutoperms($channel_id);
        }

        // if no autoperms it may be a custom role with manual perms

        if (! $perms) {
            $c = Channel::from_id($channel_id);
            if ($c) {
                $perms = Permissions::FilledPerms(explode(',',get_abconfig($channel_id, $c['channel_hash'], 'system', 'my_perms', EMPTY_STR)));
            }
        }

        // nothing was found - create a filled permission array where all permissions are 0

        if (! $perms) {
            $perms = Permissions::FilledPerms([]);
        }

        $this->permcats[] = [
            'name'      => 'default',
            'localname' => t('default', 'permcat'),
            'perms'     => Permissions::Operms($perms),
            'system'    => 1
        ];


        $p = $this->load_permcats($channel_id, $abook_id);
        if ($p) {
            for ($x = 0; $x < count($p); $x++) {
                $this->permcats[] = [
                    'name'      => $p[$x][0],
                    'localname' => $p[$x][1],
                    'perms'     => Permissions::Operms(Permissions::FilledPerms($p[$x][2])),
                    'system'    => intval($p[$x][3])
                ];
            }
        }
    }

    public function match($current) {
        if ($current) {
            $perms = Permissions::FilledPerms($current);
            $operms = Permissions::Operms($perms);
        }
        
        if ($this->permcats && $operms) {
            foreach($this->permcats as $permcat) {
                $pp = $permcat['perms'];
                $matching = 0;
                foreach ($pp as $rp) {
                    foreach ($operms as $op) {
                        if ($rp['name'] === $op['name'] && intval($rp['value']) === intval($op['value'])) {
                            $matching ++;
                            break;
                        }
                    }
                }
                if ($matching === count($pp)) {
                    return $permcat['name'];
                }
            }
        }
        return 'custom';
    }
    
    /**
     * @brief Return array with permcats.
     *
     * @return array
     */
    public function listing()
    {
        return $this->permcats;
    }

    /**
     * @brief
     *
     * @param string $name
     * @return array
     *   * \e array with permcats
     *   * \e bool \b error if $name not found in permcats true
     */
    public function fetch($name)
    {
        if ($name && $this->permcats) {
            foreach ($this->permcats as $permcat) {
                if (strcasecmp($permcat['name'], $name) === 0) {
                    return $permcat;
                }
            }
        }    
        return ['error' => true];
    }

    public function load_permcats($uid, $abook_id = 0)
    {

        $permcats = [
            [ 'follower', t('follower', 'permcat'),
                [ 'view_stream','search_stream','deliver_stream','view_profile','view_contacts','view_storage',
                    'view_pages','view_wiki', 'post_like' ], 1
            ],
            [ 'contributor', t('contributor', 'permcat'),
                [ 'view_stream','search_stream','deliver_stream','view_profile','view_contacts','view_storage',
                    'view_pages','view_wiki', 'post_wall','post_comments','write_wiki','post_like','tag_deliver',
                    'chat' ], 1
            ],
            [ 'publisher', t('publisher', 'permcat'),
                [ 'view_stream','search_stream','deliver_stream','view_profile','view_contacts','view_storage',
                    'view_pages', 'write_storage','post_wall','write_pages','write_wiki','post_comments','post_like',
                    'tag_deliver', 'chat', 'republish' ], 1
            ]
        ];

        if ($uid) {
            $x = q(
                "select * from pconfig where uid = %d and cat = 'permcat'",
                intval($uid)
            );
            if ($x) {
                foreach ($x as $xv) {
                    $value = unserialise($xv['v']);
                    $permcats[] = [ $xv['k'], $xv['k'], $value, 0 ];
                }
            }
        }

        if ($abook_id) {
            $r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d and abook_channel = %d",
                intval($abook_id),
                intval($uid)
            );
            if ($r) {
                $my_perms = explode(',', get_abconfig($uid, $r[0]['xchan_hash'], 'system', 'my_perms', EMPTY_STR));
                $permcats[] = [ 'custom', t('custom'), $my_perms, 1];
            }
            
        }
        /**
         * @hooks permcats
         *   * \e array
         */
        Hook::call('permcats', $permcats);

        return $permcats;
    }

    public static function find_permcat($arr, $name)
    {
        if ($arr && $name) {
            foreach ($arr as $p) {
                if ($p['name'] == $name) {
                    return $p['value'];
                }
            }
        }
        return false;
    }

    public static function update($channel_id, $name, $permarr)
    {
        PConfig::Set($channel_id, 'permcat', $name, $permarr);
    }

    public static function delete($channel_id, $name)
    {
        PConfig::Delete($channel_id, 'permcat', $name);
    }
}
