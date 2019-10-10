<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Keyutils;


class Jwks extends \Zotlabs\Web\Controller {

	function init() {
		
		$key = Keyutils::pemtome(get_config('system','pubkey'),$m,$e);
		$keys = [
			[
				'e'   => base64url_encode($e),
				'n'   => base64url_encode($m),
				'kty' => 'RSA',
				'kid' => 'key1'
			]
		];


		$ret = [
			'keys'                     => $keys
		];

		json_return_and_die($ret);
	}
}