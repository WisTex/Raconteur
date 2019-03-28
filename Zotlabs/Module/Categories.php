<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Categories extends Controller {


	function get() {

        $desc = t('This app allows you to add categories to posts and events.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if(! ( local_channel() && Apps::system_app_installed(local_channel(),'Categories'))) {
            return $text;
        }

	}


}
