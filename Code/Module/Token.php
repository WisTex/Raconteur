<?php

namespace Code\Module;

use App;
use DBA;
use Code\Web\Controller;
use Code\Identity\OAuth2Server;
use Code\Identity\OAuth2Storage;
use OAuth2\Request;
use OAuth2\Response;
use OAuth2\GrantType;

class Token extends Controller
{

    public function init()
    {

        logger('args: ' . print_r($_REQUEST, true));

        // workaround for HTTP-auth in CGI mode
        if (x($_SERVER, 'REDIRECT_REMOTE_USER')) {
            $userpass = base64_decode(substr($_SERVER["REDIRECT_REMOTE_USER"], 6));
            if (strlen($userpass)) {
                list($name, $password) = explode(':', $userpass);
                $_SERVER['PHP_AUTH_USER'] = $name;
                $_SERVER['PHP_AUTH_PW'] = $password;
            }
        }

        if (x($_SERVER, 'HTTP_AUTHORIZATION')) {
            $userpass = base64_decode(substr($_SERVER["HTTP_AUTHORIZATION"], 6));
            if (strlen($userpass)) {
                list($name, $password) = explode(':', $userpass);
                $_SERVER['PHP_AUTH_USER'] = $name;
                $_SERVER['PHP_AUTH_PW'] = $password;
            }
        }

        $storage = new OAuth2Storage(DBA::$dba->db);
        $server = new OAuth2Server($storage);
        // Add the "Client Credentials" grant type (it is the simplest of the grant types)
        $server->addGrantType(new GrantType\ClientCredentials($storage));
        // Add the "Authorization Code" grant type (this is where the oauth magic happens)
        $server->addGrantType(new GrantType\AuthorizationCode($storage));
        $request = Request::createFromGlobals();
        $response = $server->handleTokenRequest($request);
        $response->send();
        killme();
    }
}
