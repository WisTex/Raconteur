<?php

namespace Code\Module;

use Code\Web\Controller;

class Dircensor extends Controller
{

    public function get()
    {
        if (!is_site_admin()) {
            return;
        }

        $xchan = argv(1);
        if (!$xchan) {
            return;
        }

        $r = q(
            "select * from xchan where xchan_hash = '%s'",
            dbesc($xchan)
        );

        if (!$r) {
            return;
        }

        $val = (($r[0]['xchan_censored']) ? 0 : 1);

        q(
            "update xchan set xchan_censored = $val where xchan_hash = '%s'",
            dbesc($xchan)
        );

        if ($val) {
            info(t('Entry censored') . EOL);
        } else {
            info(t('Entry uncensored') . EOL);
        }

        goaway(z_root() . '/directory');
    }
}
