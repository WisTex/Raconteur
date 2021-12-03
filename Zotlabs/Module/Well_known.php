<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Module\Webfinger;
use Zotlabs\Module\Oauthinfo;

class Well_known extends Controller
{

    public function init()
    {

        if (argc() > 1) {
            $arr = ['server' => $_SERVER, 'request' => $_REQUEST];
            call_hooks('well_known', $arr);

            if (!check_siteallowed($_SERVER['REMOTE_ADDR'])) {
                logger('well_known: site not allowed. ' . $_SERVER['REMOTE_ADDR']);
                killme();
            }

            // from php.net re: REMOTE_HOST:
            //     Note: Your web server must be configured to create this variable.
            // For example in Apache you'll need HostnameLookups On inside httpd.conf
            // for it to exist. See also gethostbyaddr().

            if (get_config('system', 'siteallowed_remote_host') && (!check_siteallowed($_SERVER['REMOTE_HOST']))) {
                logger('well_known: site not allowed. ' . $_SERVER['REMOTE_HOST']);
                killme();
            }

            switch (argv(1)) {
                case 'webfinger':
                    App::$argc -= 1;
                    array_shift(App::$argv);
                    App::$argv[0] = 'webfinger';
                    $module = new Webfinger();
                    $module->init();
                    break;

                case 'oauth-authorization-server':
                case 'openid-configuration':
                    App::$argc -= 1;
                    array_shift(App::$argv);
                    App::$argv[0] = 'oauthinfo';
                    $module = new Oauthinfo();
                    $module->init();
                    break;

                case 'dnt-policy.txt':
                    echo file_get_contents('doc/global/dnt-policy.txt');
                    killme();

                default:
                    if (file_exists(App::$cmd)) {
                        echo file_get_contents(App::$cmd);
                        killme();
                    } elseif (file_exists(App::$cmd . '.php')) {
                        require_once(App::$cmd . '.php');
                    }
                    break;
            }
        }

        http_status_exit(404);
    }
}
