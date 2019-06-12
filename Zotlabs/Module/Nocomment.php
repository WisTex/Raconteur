<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Nocomment extends Controller {

	function get() {

        $desc = t('This app allows you to disable comments on individual posts.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if(! ( local_channel() && Apps::system_app_installed(local_channel(),'No Comment'))) {
            return $text;
        }

		$desc = t('This app is installed. A button to control the ability to comment may be found below the post editor.');

		$text = '<div class="section-content-info-wrapper">' . $desc . '</div>';		

		return $text;

	}
}
