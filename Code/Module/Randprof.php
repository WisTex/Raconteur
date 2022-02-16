<?php

namespace Code\Module;

use Code\Web\Controller;

class Randprof extends Controller
{

    public function init()
    {
        $x = random_profile();
        if ($x) {
            goaway(chanlink_hash($x));
        }

        /** FIXME this doesn't work at the moment as a fallback */
        goaway(z_root() . '/profile');
    }
}
