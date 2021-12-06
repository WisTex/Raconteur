<?php

namespace Zotlabs\Update;

class _1223
{

    public function run()
    {
        foreach (['abconfig', 'config', 'pconfig', 'xconfig', 'iconfig'] as $tbl) {
            while (1) {
                $r = q(
                    "select id, v from %s where v like '%s' limit 100 ",
                    dbesc($tbl),
                    dbesc('a:%')
                );
                if (!$r) {
                    break;
                }
                foreach ($r as $rv) {
                    $s = unserialize($rv['v']);
                    if ($s && is_array($s)) {
                        $s = serialise($s);
                    } else {
                        $s = $rv['v'];
                    }
                    q(
                        "update %s set v = '%s' where id = %d",
                        dbesc($tbl),
                        dbesc($s),
                        dbesc($rv['id'])
                    );
                }
            }
        }
        return UPDATE_SUCCESS;
    }
}
