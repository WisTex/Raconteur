<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Expire extends Controller {


	function get() {

        $desc = t('This app allows you to set an optional expiration date/time for posts, after which they will be deleted. This must be at least fifteen minutes into the future.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if(! ( local_channel() && Apps::system_app_installed(local_channel(),'Expire Posts'))) {
            return $text;
        }

	}


}
