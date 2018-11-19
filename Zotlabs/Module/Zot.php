<?php
/**
 * @file Zotlabs/Module/Zot.php
 *
 * @brief Zot endpoint.
 *
 */

namespace Zotlabs\Module;

use Zotlabs\Zot6 as ZotProtocol;

/**
 * @brief Zot module.
 *
 */

class Zot extends \Zotlabs\Web\Controller {

	function init() {
		$zot = new ZotProtocol\Receiver(new ZotProtocol\Zot6Handler());
		json_return_and_die($zot->run(),'application/x-zot+json');
	}

}
