<?php

namespace Code\Module;

use App;
use Code\Lib\Apps;
use Code\Lib\Libprofile;
use Code\Web\Controller;
use Code\Render\Comanche;

class Categories extends Controller
{

    public function init()
    {

        if (local_channel()) {
            $channel = App::get_channel();
            $which = '';
            if ($channel && $channel['channel_address']) {
                $which = $channel['channel_address'];
            }
            Libprofile::load($which, 0);
        }
    }


    public function get()
    {

        $desc = t('This app allows you to add categories to posts and events.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Categories'))) {
            return $text;
        }

        $c = new Comanche();
        return $c->widget('catcloud', EMPTY_STR);
    }
}
