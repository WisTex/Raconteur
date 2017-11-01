<?php

namespace Zotlabs\Module;

class Cloud_tiles extends \Zotlabs\Web\Controller {

	function init() {

		if(intval($_SESSION['cloud_tiles']))
			$_SESSION['cloud_tiles'] = 0;
		else
			$_SESSION['cloud_tiles'] = 1;

		goaway(z_root() . '/' . hex2bin(argv(1)));

	}
}