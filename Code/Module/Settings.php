<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Web\SubModule;
use Code\Lib\Navbar;

require_once('include/security.php');

class Settings extends Controller
{

    private $sm = null;

    public function init()
    {

        if (!local_channel()) {
            return;
        }

        if ($_SESSION['delegate']) {
            return;
        }

        App::$profile_uid = local_channel();

        // default is channel settings in the absence of other arguments

        if (argc() == 1) {
            // We are setting these values - don't use the argc(), argv() functions here
            App::$argc = 2;
            App::$argv[] = 'channel';
        }

        $this->sm = new SubModule();

        if ($this->sm->call('init') !== false) {
            return;
        }
    }

    public function post()
    {

        if (!local_channel()) {
            return;
        }

        if ($_SESSION['delegate']) {
            return;
        }

        // logger('mod_settings: ' . print_r($_REQUEST,true));

        if (argc() > 1) {
            if ($this->sm->call('post') !== false) {
                return;
            }
        }

        goaway(z_root() . '/settings');
    }


    public function get()
    {

        Navbar::set_selected('Settings');

        if ((!local_channel()) || ($_SESSION['delegate'])) {
            notice(t('Permission denied.') . EOL);
            return login();
        }


        $channel = App::get_channel();
        if ($channel) {
            head_set_icon($channel['xchan_photo_s']);
        }

        $o = $this->sm->call('get');
        if ($o !== false) {
            return $o;
        }

        $o = EMPTY_STR;
    }
}
