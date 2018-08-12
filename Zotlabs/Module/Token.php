<?php

namespace Zotlabs\Module;

use Zotlabs\Identity\OAuth2Storage;


class Token extends \Zotlabs\Web\Controller {

	function init() {

		// workaround for HTTP-auth in CGI mode
		if (x($_SERVER, 'REDIRECT_REMOTE_USER')) {
			$userpass = base64_decode(substr($_SERVER["REDIRECT_REMOTE_USER"], 6)) ;
			if(strlen($userpass)) {
				list($name, $password) = explode(':', $userpass);
				$_SERVER['PHP_AUTH_USER'] = $name;
				$_SERVER['PHP_AUTH_PW'] = $password;
			}
		}

		if (x($_SERVER, 'HTTP_AUTHORIZATION')) {
			$userpass = base64_decode(substr($_SERVER["HTTP_AUTHORIZATION"], 6)) ;
			if(strlen($userpass)) {
				list($name, $password) = explode(':', $userpass);
				$_SERVER['PHP_AUTH_USER'] = $name;
				$_SERVER['PHP_AUTH_PW'] = $password;
			}
		}
                $storage = new OAuth2Storage(\DBA::$dba->db);
		$s = new \Zotlabs\Identity\OAuth2Server($storage);
		$request = \OAuth2\Request::createFromGlobals();
		$response = $s->handleTokenRequest($request);
                $response->send();
		killme();
	}

}
