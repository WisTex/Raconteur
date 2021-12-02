<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Future extends Controller
{

    public function get()
    {

        $desc = t('This app allows you to set an optional publish date/time for posts, which may be in the future. This must be at least ten minutes into the future to initiate delayed publishing. The posts will be published automatically after that time has passed. Once installed, a new button will appear in the post editor to set the date/time.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        return $text;

    }

}
