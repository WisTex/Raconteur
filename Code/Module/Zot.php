<?php
namespace Code\Module;
/**
 * @file Code/Module/Zot.php
 *
 * @brief Zot endpoint
 */
use Code\Web\Controller;
use Code\Nomad\Receiver;
use Code\Nomad\NomadHandler;
/**
 * @brief Zot module.
 *
 */
class Zot extends Controller
{
	public function init()
	{
		$zot = new Receiver(new NomadHandler());
		json_return_and_die($zot->run(),'application/x-nomad+json');
	}
}
