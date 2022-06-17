<?php

namespace Code\Module;

use App;
use Exception;
use OAuth1Request;
use OAuth1Consumer;
use OAuth1Util;
use Code\Web\Controller;
use Code\Extend\Hook;
use Code\Render\Theme;


require_once('include/api.php');

class Api extends Controller
{


    public function init()
    {
        zot_api_init();

        api_register_func('api/client/register', 'api_client_register', false);
        api_register_func('api/oauth/request_token', 'api_oauth_request_token', false);
        api_register_func('api/oauth/access_token', 'api_oauth_access_token', false);

        $args = [];
        Hook::call('api_register', $args);

        return;
    }

    public function post()
    {
        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }
    }

    public function get()
    {
        echo api_call();
        killme();
    }

    public function oauth_get_client($request)
    {

        $params = $request->get_parameters();
        $token = $params['oauth_token'];

        $r = q(
            "SELECT clients.* FROM clients, tokens WHERE clients.client_id = tokens.client_id 
			AND tokens.id = '%s' AND tokens.auth_scope = 'request' ",
            dbesc($token)
        );
        if ($r) {
            return $r[0];
        }

        return null;
    }
}
