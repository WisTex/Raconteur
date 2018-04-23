<?php
/**
 * @file Zotlabs/Module/Zot.php
 *
 * @brief Mercury endpoint.
 *
 */

namespace Zotlabs\Module;

/**
 * @brief Zot module.
 *
 */

class Zot extends \Zotlabs\Web\Controller {

	function init() {
		$zot = new \Zotlabs\Zot6\Receiver(new Zotlabs\Zot6\Zot6Handler());
		$zot->run();
		exit;
	}

}
