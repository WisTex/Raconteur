<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Channel;

require_once('include/photo_factory.php');
require_once('include/photos.php');


class Wall_upload extends Controller
{

    public function post()
    {


        $using_api = ((x($_FILES, 'media')) ? true : false);

        if ($using_api) {
            require_once('include/api.php');
            if (api_user()) {
                $channel = Channel::from_id(api_user());
            }
        } else {
            if (argc() > 1) {
                $channel = Channel::from_username(argv(1));
            }
        }

        if (!$channel) {
            if ($using_api) {
                return;
            }
            notice(t('Channel not found.') . EOL);
            killme();
        }

        $observer = App::get_observer();

        $args = array('source' => 'editor', 'visible' => 0, 'contact_allow' => array($channel['channel_hash']));

        $ret = photo_upload($channel, $observer, $args);

        if (!$ret['success']) {
            if ($using_api) {
                return;
            }
            notice($ret['message']);
            killme();
        }

        if ($using_api) {
            return ("\n\n" . $ret['body'] . "\n\n");
        } else {
            echo "\n\n" . $ret['body'] . "\n\n";
        }
        killme();
    }
}
