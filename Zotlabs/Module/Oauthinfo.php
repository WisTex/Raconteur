<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Oauthinfo extends Controller
{

    public function init()
    {

        $ret = [
            'issuer' => z_root(),
            'authorization_endpoint' => z_root() . '/authorize',
            'jwks_uri' => z_root() . '/jwks',
            'token_endpoint' => z_root() . '/token',
            'userinfo_endpoint' => z_root() . '/userinfo',
            'scopes_supported' => ['openid', 'profile', 'email'],
            'response_types_supported' => ['code', 'token', 'id_token', 'code id_token', 'token id_token']
        ];

        json_return_and_die($ret);
    }
}
