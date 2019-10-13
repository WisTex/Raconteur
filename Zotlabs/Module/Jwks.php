<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Keyutils;
use Zotlabs\Web\Controller;

class Jwks extends Controller {

	function init() {
		
		$key = Keyutils::pemtome(get_config('system','pubkey'),$m,$e);
		$keys = [
			[
				'alg' => 'RS256',
				'e'   => base64url_encode($e),
				'n'   => base64url_encode($m),
				'kty' => 'RSA',
				'kid' => 'key1'
			]
		];


		$ret = [
			'keys' => $keys
		];

		if (argc() > 1) {
			$entry = intval(argv(1));
			if ($keys[$entry]) {
				unset($keys[$entry]['kid']);
				json_return_and_die($keys[$entry],'application/jwk+json');
			}
		}

		json_return_and_die($ret,'application/jwk-set+json');
		
	}
}