<?php

namespace Code\Module;

use Code\Web\Controller;

class Q extends Controller
{

    public function init()
    {

        $ret = ['success' => false];

        $h = argv(1);
        if (!$h) {
            json_return_and_die($ret);
        }

        $r = q(
            "select * from hubloc left join site on hubloc_url = site_url where hubloc_hash = '%s' and site_dead = 0",
            dbesc($h)
        );
        if ($r) {
            $ret['success'] = true;
            $ret['results'] = ids_to_array($r, 'hubloc_id_url');
        }
        json_return_and_die($ret);
    }
}
