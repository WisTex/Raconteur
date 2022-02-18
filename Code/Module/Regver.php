<?php
namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Account;
    
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
            if (!Account::deny($hash)) {
                killme();
            }
        }

        if ($cmd === 'allow') {
            if (!Account::approve($hash)) {
                killme();
            }
        }
    }
}
