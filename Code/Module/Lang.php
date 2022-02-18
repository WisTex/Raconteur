<?php

namespace Code\Module;

use App;
use Code\Lib\Apps;
use Code\Web\Controller;
use Code\Lib\Navbar;

class Lang extends Controller
{

    public function get()
    {

        if (local_channel()) {
            if (!Apps::system_app_installed(local_channel(), 'Language')) {
                //Do not display any associated widgets at this point
                App::$pdl = '';

                $o = '<b>Language App (Not Installed):</b><br>';
                $o .= t('Change UI language');
                return $o;
            }
        }

        Navbar::set_selected('Language');
        return lang_selector();
    }
}
