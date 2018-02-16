<?php

namespace Zotlabs\Identity;

class OAuth2Server {

	public $server;

	public function __construct() {

		$storage = new OAuth2Storage(\DBA::$dba->db);

		$config = [
			'use_openid_connect' => true,
			'issuer' => \Zotlabs\Lib\System::get_site_name()
		];

		// Pass a storage object or array of storage objects to the OAuth2 server class
		$this->server = new \OAuth2\Server($storage,$config);

		// Add the "Client Credentials" grant type (it is the simplest of the grant types)
		$this->server->addGrantType(new \OAuth2\GrantType\ClientCredentials($storage));

		// Add the "Authorization Code" grant type (this is where the oauth magic happens)
		$this->server->addGrantType(new \OAuth2\GrantType\AuthorizationCode($storage));

		$keyStorage = new \OAuth2\Storage\Memory( [ 
			'keys' => [ 
				'public_key'  => get_config('system','pubkey'),
				'private_key' => get_config('system','prvkey')
			]
		]);

		$this->server->addStorage($keyStorage,'public_key');

	}

	public function get_server() {
		return $this->server;
	} 


}