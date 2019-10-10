<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Keyutils;


class Oauthinfo extends \Zotlabs\Web\Controller {

	function init() {

		
		$key = Keyutils::pemtome(get_config('system','pubkey'),$m,$e);
		$keys = [
			[
				'alg' => 'RS256',
				'e'   => base64url_encode($e),
				'n'   => base64url_encode($m),
				'kty' => 'RSA',
				'kid' => 'rsa1'
			]
		];


		$ret = [
			'issuer'                   => z_root(),
			'authorization_endpoint'   => z_root() . '/authorize',
			'jwks_uri'                 => z_root() . '/jwks',
			'token_endpoint'           => z_root() . '/token',
			'userinfo_endpoint'        => z_root() . '/userinfo',
			'scopes_supported'         => [ 'openid', 'profile', 'email' ],
			'response_types_supported' => [ 'code', 'token', 'id_token', 'code id_token', 'token id_token' ],
			'keys'                     => $keys
		];

		json_return_and_die($ret);
	}
}