<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Account;
    
class Regmod extends Controller
{

    public function get()
    {

        global $lang;

        $_SESSION['return_url'] = App::$cmd;

        if (!local_channel()) {
            info(t('Please login.') . EOL);
            return login();
        }

        if (!is_site_admin()) {
            notice(t('Permission denied.') . EOL);
            return '';
        }

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
            if (!Account::allow($hash)) {
                killme();
            }
        }

        goaway('/admin/accounts');
    }
}
