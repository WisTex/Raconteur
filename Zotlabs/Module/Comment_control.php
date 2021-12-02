<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Comment_control extends Controller
{

    public function get()
    {

        $desc = t('This app allows you to select the comment audience and set a length of time that comments on a particular post will be accepted.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Comment Control'))) {
            return $text;
        }

        $desc = t('This app is installed. A button to control comments may be found below the post editor.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        return $text;

    }
}
