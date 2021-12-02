<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Web\Controller;
use Zotlabs\Render\Comanche;

class Tagadelic extends Controller
{

    public function init()
    {

        if (local_channel()) {
            $channel = App::get_channel();
            if ($channel && $channel['channel_address']) {
                $which = $channel['channel_address'];
            }
            Libprofile::load($which, 0);
        }

    }


    public function get()
    {

        $desc = t('This app displays a hashtag cloud on your channel homepage.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Tagadelic'))) {
            return $text;
        }

        $desc = t('This app is installed. It displays a hashtag cloud on your channel homepage.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';


        $c = new Comanche();
        return $text . EOL . EOL . $c->widget('tagcloud_wall', EMPTY_STR);

    }


}
