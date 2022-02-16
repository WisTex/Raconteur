<?php

namespace Code\Module;

use Code\Lib\Apps;
use Code\Lib\Libsync;
use Code\Web\Controller;

class Drafts extends Controller
{

    public function init()
    {
        if (local_channel() && Apps::system_app_installed(local_channel(), 'Drafts')) {
            goaway(z_root() . '/stream/?draft=1');
        }
    }


    public function get()
    {

        $desc = t('This app allows you to save posts you are writing and finish them later prior to sharing/publishing.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Drafts'))) {
            return $text;
        }
    }
}
