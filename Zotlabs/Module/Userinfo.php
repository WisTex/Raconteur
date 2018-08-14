<?php

namespace Zotlabs\Module;

use Zotlabs\Identity\OAuth2Storage;


class Userinfo extends \Zotlabs\Web\Controller {

	function init() {
		$s = new \Zotlabs\Identity\OAuth2Server(new OAuth2Storage(\DBA::$dba->db));
		$request = \OAuth2\Request::createFromGlobals();
		$s->handleUserInfoRequest($request)->send();
		killme();
	}

}
