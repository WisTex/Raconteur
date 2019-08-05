<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Safe extends Controller {

	function init() {

		if (array_key_exists('unsafe',$_SESSION) && intval($_SESSION['unsafe'])) {
			$_SESSION['unsafe'] = 0;
		}
		else {
			$_SESSION['unsafe'] = 1;
		}
		goaway(z_root());
	}



}