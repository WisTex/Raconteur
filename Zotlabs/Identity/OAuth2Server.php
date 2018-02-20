<?php

namespace Zotlabs\Identity;

class OAuth2Server extends \OAuth2\Server {

	public function __construct(OAuth2Storage $storage, $config = []) {

		if(! is_array($config)) {
			$config = [
				'use_openid_connect' => true,
				'issuer' => \Zotlabs\Lib\System::get_site_name()
			];
		}

		parent::__construct($storage, $config);

		// Add the "Client Credentials" grant type (it is the simplest of the grant types)
		$this->addGrantType(new \OAuth2\GrantType\ClientCredentials($storage));

		// Add the "Authorization Code" grant type (this is where the oauth magic happens)
		$this->addGrantType(new \OAuth2\GrantType\AuthorizationCode($storage));

		$keyStorage = new \OAuth2\Storage\Memory( [
			'keys' => [
				'public_key'  => get_config('system', 'pubkey'),
				'private_key' => get_config('system', 'prvkey')
			]
		]);

		$this->addStorage($keyStorage, 'public_key');
	}

}
