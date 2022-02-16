<?php

namespace Code\Module;

use Code\Web\Controller;

class Login extends Controller
{

    public function get()
    {
        if (local_channel()) {
            goaway(z_root());
        }
        if (remote_channel() && $_SESSION['atoken']) {
            goaway(z_root());
        }

        return login(true);
    }
}
