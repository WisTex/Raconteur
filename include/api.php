<?php

use Zotlabs\Lib\Api_router;
use Zotlabs\Extend\Hook;

require_once("include/conversation.php");
require_once("include/html2plain.php");
require_once('include/security.php');
require_once('include/photos.php');
require_once('include/attach.php');
require_once('include/api_auth.php');
require_once('include/api_zot.php');

    /*
     *
     * Zot API.
     *
     */


    $API = [];

    $called_api = null;

    // All commands which require authentication accept a "channel" parameter
    // which is the left hand side of the channel address/nickname.
    // If provided, the desired channel is selected before carrying out the command.
    // If not provided, the default channel associated with the account is used.
    // If channel selection fails, the API command requiring login will fail.

function api_user()
{
    $aid = get_account_id();
    $channel = App::get_channel();
        
    if ($aid && isset($_REQUEST['channel']) && $_REQUEST['channel']) {
        // Only change channel if it is different than the current channel

        if ($channel && isset($channel['channel_address']) && $channel['channel_address'] !== $_REQUEST['channel']) {
            $c = q(
                "select channel_id from channel where channel_address = '%s' and channel_account_id = %d limit 1",
                dbesc($_REQUEST['channel']),
                intval($aid)
            );
            if ((! $c) || (! change_channel($c[0]['channel_id']))) {
                return false;
            }
        }
    }
    if (isset($_SESSION['allow_api']) && $_SESSION['allow_api']) {
        return local_channel();
    }
    return false;
}


function api_date($str)
{
    // Wed May 23 06:01:13 +0000 2007
    return datetime_convert('UTC', 'UTC', $str, 'D M d H:i:s +0000 Y');
}


function api_register_func($path, $func, $auth = false)
{
    Api_router::register($path, $func, $auth);
}

    
    /**************************
     *  MAIN API ENTRY POINT  *
     **************************/

function api_call()
{

    $p    = App::$cmd;
    $type = null;

    if (strrpos($p, '.')) {
        $type = substr($p, strrpos($p, '.')+1);
        if (strpos($type, '/') === false) {
            $p = substr($p, 0, strrpos($p, '.'));
            // recalculate App argc,argv since we just extracted the type from it
            App::$argv = explode('/', $p);
            App::$argc = count(App::$argv);
        }
    }

    if ((! $type) || (! in_array($type, [ 'json', 'xml', 'rss', 'as', 'atom' ]))) {
        $type = 'json';
    }

    $info = Api_router::find($p);

    if (in_array($type, [ 'rss', 'atom', 'as' ])) {
        // These types no longer supported.
        $info = false;
    }

    logger('API info: ' . $p . ' type: ' . $type . ' ' . print_r($info, true), LOGGER_DEBUG, LOG_INFO);

    if ($info) {
        if ($info['auth'] === true && api_user() === false) {
            api_login();
        }

        load_contact_links(api_user());

        $channel = App::get_channel();

        logger('API call for ' . ((isset($channel) && is_array($channel)) ? $channel['channel_name'] : '') . ': ' . App::$query_string);
        logger('API parameters: ' . print_r($_REQUEST, true));

        $r = call_user_func($info['func'], $type);

        if ($r === false) {
            return;
        }

        switch ($type) {
            case 'xml':
                header('Content-Type: text/xml');
                return $r;
                break;
            case 'json':
                header('Content-Type: application/json');
                // Lookup JSONP to understand these lines. They provide cross-domain AJAX ability.
                if ($_GET['callback']) {
                    $r = $_GET['callback'] . '(' . $r . ')' ;
                }
                return $r;
                break;
        }
    }


    $x = [ 'path' => App::$query_string ];
    Hook::call('api_not_found', $x);

    header('HTTP/1.1 404 Not Found');
    logger('API call not implemented: ' . App::$query_string . ' - ' . print_r($_REQUEST, true));
    $r = '<status><error>not implemented</error></status>';
    switch ($type) {
        case 'xml':
            header('Content-Type: text/xml');
            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $r;
            break;
        case "json":
            header('Content-Type: application/json');
            return json_encode(array('error' => 'not implemented'));
            break;
        case "rss":
            header('Content-Type: application/rss+xml');
            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $r;
            break;
        case "atom":
            header('Content-Type: application/atom+xml');
            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $r;
            break;
    }
}

    /**
     *  load api $templatename for $type and replace $data array
     */

function api_apply_template($templatename, $type, $data)
{

    switch ($type) {
        case 'xml':
            if ($data) {
                foreach ($data as $k => $v) {
                    $ret = arrtoxml(str_replace('$', '', $k), $v);
                }
            }
            break;
        case 'json':
        default:
            if ($data) {
                foreach ($data as $rv) {
                    $ret = json_encode($rv);
                }
            }
            break;
    }

    return $ret;
}
    

function api_client_register($type)
{

    logger('api_client_register: ' . print_r($_REQUEST, true));
        
    $ret = [];
    $key = random_string(16);
    $secret = random_string(16);
    $name = trim(escape_tags($_REQUEST['client_name']));
    if (! $name) {
        json_return_and_die($ret);
    }
    if (is_array($_REQUEST['redirect_uris'])) {
        $redirect = trim($_REQUEST['redirect_uris'][0]);
    } else {
        $redirect = trim($_REQUEST['redirect_uris']);
    }
    $grant_types = trim($_REQUEST['grant_types']);
    $scope = trim($_REQUEST['scopes']);
    $icon = trim($_REQUEST['logo_uri']);

    $r = q(
        "INSERT INTO oauth_clients (client_id, client_secret, redirect_uri, grant_types, scope, user_id, client_name)
			VALUES ( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ",
        dbesc($key),
        dbesc($secret),
        dbesc($redirect),
        dbesc($grant_types),
        dbesc($scope),
        dbesc((string) api_user()),
        dbesc($name)
    );

    $ret['client_id'] = $key;
    $ret['client_secret'] = $secret;
    $ret['expires_at'] = 0;
    json_return_and_die($ret);
}

