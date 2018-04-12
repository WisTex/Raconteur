<?php

namespace Zotlabs\Module;

class Logout extends \Zotlabs\Web\Controller {

	function init() {
		if($_SESSION['delegate'] && $_SESSION['delegate_push']) {
			$_SESSION = $_SESSION['delegate_push'];
		}
		else {	
			\App::$session->nuke();
		}
		goaway(z_root());

	}
}
