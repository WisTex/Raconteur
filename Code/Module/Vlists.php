<?php

namespace Code\Module;

use Code\Lib\Apps;
use Code\Lib\Libsync;
use Code\Web\Controller;

class Vlists extends Controller
{


    public function get()
    {

        $desc = t('This app creates dynamic access lists corresponding to [1] all connections, [2] all ActivityPub protocol connections, and [3] all Nomad or Zot/6 protocol connections. These additional selections will be found within the Permissions setting tool.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        $o .= $text;


        $text2 = '<div class="section-content-info-wrapper">' . t('This app is installed. ') . '</div>';

        if (local_channel() && Apps::system_app_installed(local_channel(), 'Virtual Lists')) {
            $o .= $text2;
        }

        return $o;
    }
}
