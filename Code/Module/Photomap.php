<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Apps;

class Photomap extends Controller
{


    public function get()
    {
        $desc = t('This app provides a displayable map when viewing detail of photos that contain location information.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Photomap'))) {
            return $text;
        }

        return $text . '<br><br>' . t('This app is currently installed.');
    }
}
