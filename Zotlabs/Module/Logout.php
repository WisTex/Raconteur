<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;

class Logout extends Controller
{

    public function init()
    {
        if ($_SESSION['delegate'] && $_SESSION['delegate_push']) {
            $_SESSION = $_SESSION['delegate_push'];
        } else {
            App::$session->nuke();
        }
        goaway(z_root());
    }
}
