<?php

namespace Code\Web;

use App;
use Code\Lib\Channel;
use Code\Extend\Hook;

class WebServer
{

    public function run()
    {


        /*
         * Bootstrap the application, load configuration, load modules, load theme, etc.
         */

        require_once('boot.php');

        if (file_exists('maintenance_lock') || file_exists('cache/maintenance_lock')) {
            http_status_exit(503, 'System unavailable');
        }

        sys_boot();

        $this->start_session();

        $this->set_language();

        $this->set_identities();

        $this->initialise_notifications();

        if (App::$install) {
            /*
             * During installation, only permit the view module and setup module.
             * The view module is required to expand/replace variables in style.css
             */

            if (App::$module !== 'view') {
                App::$module = 'setup';
            }
        } else {
            /*
             * check_config() is responsible for running update scripts. These automatically
             * update the DB schema whenever we push a new one out. It also checks to see if
             * any plugins have been added or removed and reacts accordingly.
             */

            check_config();
        }

        $this->create_channel_links();

        $this->initialise_content();

        $Router = new Router();
        $Router->Dispatch();

        // if the observer is a visitor, add some javascript to the page to let
        // the application take them home.

        $this->set_homebase();

        // now that we've been through the module content, see if the page reported
        // a permission problem via session based notifications and if so, a 403
        // response would seem to be in order.

        if (is_array($_SESSION['sysmsg']) && stristr(implode("", $_SESSION['sysmsg']), t('Permission denied'))) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 ' . t('Permission denied.'));
        }

        construct_page();

        killme();
    }

    private function start_session()
    {

        if (App::$session) {
            App::$session->start();
        } else {
            session_start();
            register_shutdown_function('session_write_close');
        }
    }

    private function set_language()
    {

        /*
         * Determine the language of the interface
         */

        // First use the browser preference, if available. This will fall back to 'en'
        // if there is no built-in language support for the preferred languagge


        App::$language = get_best_language();
        load_translation_table(App::$language, App::$install);

        // See if there's a request to over-ride the language
        // store it in the session.

        if (array_key_exists('system_language', $_REQUEST)) {
            if (strlen($_REQUEST['system_language'])) {
                $_SESSION['language'] = $_REQUEST['system_language'];
            } else {
                // reset to default if it's an empty string
                unset($_SESSION['language']);
            }
        }

        // If we've over-ridden the language, set it now.

        if ((x($_SESSION, 'language')) && ($_SESSION['language'] !== App::$language)) {
            App::$language = $_SESSION['language'];
            load_translation_table(App::$language);
        }
    }

    private function set_identities()
    {

        if ((x($_GET, 'zid')) && (! App::$install)) {
            App::$query_string = strip_zids(App::$query_string);
            if (! local_channel()) {
                if ($_SESSION['my_address'] !== $_GET['zid']) {
                    $_SESSION['my_address'] = $_GET['zid'];
                    $_SESSION['authenticated'] = 0;
                }
                Channel::zid_init();
            }
        }

        if ((x($_GET, 'zat')) && (! App::$install)) {
            App::$query_string = strip_zats(App::$query_string);
            if (! local_channel()) {
                Channel::zat_init();
            }
        }

        if ((x($_REQUEST, 'owt')) && (! App::$install)) {
            $token = $_REQUEST['owt'];
            App::$query_string = strip_query_param(App::$query_string, 'owt');
            owt_init($token);
        }

        if ((x($_SESSION, 'authenticated')) || (x($_POST, 'auth-params')) || (App::$module === 'login')) {
            require('include/auth.php');
        }
    }

    private function initialise_notifications()
    {
        if (! x($_SESSION, 'sysmsg')) {
            $_SESSION['sysmsg'] = [];
        }

        if (! x($_SESSION, 'sysmsg_info')) {
            $_SESSION['sysmsg_info'] = [];
        }
    }

    private function initialise_content()
    {

        /* initialise content region */

        if (! x(App::$page, 'content')) {
            App::$page['content'] = EMPTY_STR;
        }

        Hook::call('page_content_top', App::$page['content']);
    }

    private function create_channel_links()
    {

        /* Initialise the Link: response header if this is a channel page.
         * This cannot be done inside the channel module because some protocol
         * addons over-ride the module functions and these links are common
         * to all protocol drivers; thus doing it here avoids duplication.
         */

        if (( App::$module === 'channel' ) && argc() > 1) {
            App::$channel_links = [
                [
                    'rel'  => 'jrd',
                    'type' => 'application/jrd+json',
                    'href'  => z_root() . '/.well-known/webfinger?f=&resource=acct%3A' . argv(1) . '%40' . App::get_hostname()
                ],

                [
                    'rel'  => 'alternate',
                    'type' => 'application/x-zot+json',
                    'href'  => z_root() . '/channel/' . argv(1)
                ],

				[
					'rel'  => 'alternate',
					'type' => 'application/x-nomad+json',
					'href'  => z_root() . '/channel/' . argv(1)
				],

                [
                    'rel'  => 'self',
                    'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
                    'href' => z_root() . '/channel/' . argv(1)
                ],

                [
                    'rel'  => 'self',
                    'type' => 'application/activity+json',
                    'href' => z_root() . '/channel/' . argv(1)
                ]
            ];

            $x = [ 'channel_address' => argv(1), 'channel_links' => App::$channel_links ];
            Hook::call('channel_links', $x);
            App::$channel_links = $x['channel_links'];
            header('Link: ' . App::get_channel_links());
        }
    }

    private function set_homebase()
    {

        // If you're just visiting, let javascript take you home

        if (x($_SESSION, 'visitor_home')) {
            $homebase = $_SESSION['visitor_home'];
        } elseif (local_channel()) {
            $homebase = z_root() . '/channel/' . App::$channel['channel_address'];
        }

        if (isset($homebase)) {
            App::$page['content'] .= '<script>var homebase = "' . $homebase . '";</script>';
        }
    }
}
