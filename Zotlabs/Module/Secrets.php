<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Secrets extends Controller
{

    public function get()
    {

        $desc = t('This app allows you to protect messages with a secret passphrase. This only works across selected platforms.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Secrets'))) {
            return $text;
        }

        $desc = t('This app is installed. A button to encrypt content may be found in the post editor.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        return $text;

    }
}
