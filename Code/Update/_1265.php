<?php

namespace Code\Update;


use Code\Access\PermissionRoles;
use Code\Access\Permissions;
use Code\Lib\AbConfig;

class _1265
{
    public function run() {
        $r = q("select * from abook where abook_pending = 0 and abook_self = 0");
        if ($r) {
            foreach ($r as $ab) {
                $their_perms = AbConfig::Get($ab['abook_channel'], $ab['abook_xchan'], 'system', 'their_perms', '');
                if ($their_perms) {
                    continue;
                }
                $x = PermissionRoles::role_perms('social');
                $p = Permissions::FilledPerms($x['perms_connect']);
                $their_perms = Permissions::serialise($p);
                AbConfig::Set($ab['abook_channel'], $ab['abook_xchan'], 'system', 'their_perms', $their_perms);
            }
        }
        return UPDATE_SUCCESS;
    }

    public function verify() {
        return true;
    }

}
