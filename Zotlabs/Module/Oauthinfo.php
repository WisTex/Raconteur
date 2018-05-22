<?php

namespace Zotlabs\Module;


class Oauthinfo extends \Zotlabs\Web\Controller {


	function init() {

		$ret = [
			'issuer'                   => z_root(),
			'authorization_endpoint'   => z_root() . '/authorize',
			'token_endpoint'           => z_root() . '/token',
			'response_types_supported' => [ 'code', 'code token' ] 
		];


		json_return_and_die($ret);
	}


}