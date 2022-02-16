<?php

namespace Code\Module;

use Code\Web\Controller;

class Safe extends Controller
{

    public function init()
    {

        $x = get_safemode();
        if ($x) {
            $_SESSION['safemode'] = 0;
        } else {
            $_SESSION['safemode'] = 1;
        }
        goaway(z_root());
    }
}
