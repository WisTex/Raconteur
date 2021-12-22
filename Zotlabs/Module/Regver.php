<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;

class Regver extends Controller
{

    public function get()
    {

        $_SESSION['return_url'] = App::$cmd;

        if (argc() != 3) {
            killme();
        }

        $cmd = argv(1);
        $hash = argv(2);

        if ($cmd === 'deny') {
            if (!account_deny($hash)) {
                killme();
            }
        }

        if ($cmd === 'allow') {
            if (!account_approve($hash)) {
                killme();
            }
        }
    }
}
