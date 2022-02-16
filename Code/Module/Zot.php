<?php

/**
 * @file Code/Module/Zot.php
 *
 * @brief Zot endpoint.
 *
 */

namespace Code\Module;

use Code\Web\Controller;
use Code\Zot6\Receiver;
use Code\Zot6\Zot6Handler;

/**
 * @brief Zot module.
 *
 */
class Zot extends Controller
{
	public function init()
	{
		$zot = new Receiver(new Zot6Handler());
		json_return_and_die($zot->run(),'application/x-nomad+json');
	}

}
