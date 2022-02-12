<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;
use Zotlabs\Render\Theme;


class Expire extends Controller
{

    public function post()
    {

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Expire Posts'))) {
            return;
        }

        if ($_POST['expire-submit']) {
            $expire = intval($_POST['selfexpiredays']);
            if ($expire < 0) {
                $expire = 0;
            }
            set_pconfig(local_channel(), 'system', 'selfexpiredays', $expire);
            info(t('Expiration settings updated.') . EOL);
        }

        Libsync::build_sync_packet();
    }


    public function get()
    {

        $desc = t('This app allows you to set an optional expiration date/time for your own posts, after which they will be deleted. This must be at least fifteen minutes into the future. You may also choose to automatically delete all your posts after a set number of days');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Expire Posts'))) {
            return $text;
        }

        $setting_fields .= replace_macros(Theme::get_template('field_input.tpl'), [
            '$field' => array('selfexpiredays', t('Expire and delete all my posts after this many days'), intval(get_pconfig(local_channel(), 'system', 'selfexpiredays', 0)), t('Leave at 0 if you wish to manually control expiration of specific posts.'))
        ]);

        return replace_macros(Theme::get_template('generic_app_settings.tpl'), [
            '$addon' => array('expire', t('Automatic Expiration Settings'), '', t('Submit')),
            '$content' => $setting_fields
        ]);
    }
}
