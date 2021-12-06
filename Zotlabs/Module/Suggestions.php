<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

require_once('include/socgraph.php');

class Suggestions extends Controller
{

    public function init()
    {
        if (!local_channel()) {
            return;
        }

        if (x($_GET, 'ignore')) {
            q(
                "insert into xign ( uid, xchan ) values ( %d, '%s' ) ",
                intval(local_channel()),
                dbesc($_GET['ignore'])
            );
            Libsync::build_sync_packet(local_channel(), ['xign' => [['uid' => local_channel(), 'xchan' => $_GET['ignore']]]]);
        }
    }


    public function get()
    {

        $o = '';
        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        if (Apps::system_app_installed(local_channel(), 'Suggest Channels')) {
            goaway(z_root() . '/directory?f=&suggest=1');
        }

        $desc = t('This app (when installed) displays a small number of friend suggestions on selected pages or you can run the app to display a full list of channel suggestions.');

        return '<div class="section-content-info-wrapper">' . $desc . '</div>';
    }
}
