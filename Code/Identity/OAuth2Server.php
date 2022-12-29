<?php

namespace Code\Identity;

use Code\Lib\System;
use OAuth2\Server;
use OAuth2\Storage\Memory;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\GrantType\RefreshToken;
use OAuth2\OpenID\GrantType\AuthorizationCode;

class OAuth2Server extends Server
{

    public function __construct(OAuth2Storage $storage, $config = null)
    {

        if (! is_array($config)) {
            $config = [
//              'use_openid_connect' => true,
                'issuer' => System::get_site_name(),
//              'use_jwt_access_tokens' => true,
//              'enforce_state' => false
            ];
        }

        parent::__construct($storage, $config);

        // Add the "Client Credentials" grant type (it is the simplest of the grant types)
        $this->addGrantType(new ClientCredentials($storage));

        // Add the "Authorization Code" grant type (this is where the oauth magic happens)
        // Need to use OpenID\GrantType to return id_token
        // (see:https://github.com/bshaffer/oauth2-server-php/issues/443)
        $this->addGrantType(new AuthorizationCode($storage));
        // Add the "Refresh Token" grant type
        $this->addGrantType(new RefreshToken($storage));
        $keyStorage = new Memory([
            'keys' => [
                'public_key'  => get_config('system', 'pubkey'),
                'private_key' => get_config('system', 'prvkey')
            ]
        ]);

        $this->addStorage($keyStorage, 'public_key');
    }
}
