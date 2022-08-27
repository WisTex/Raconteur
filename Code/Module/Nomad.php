<?php
namespace Code\Module;
/**
 * @file Code/Module/Nomad.php
 *
 * @brief Nomad endpoint
 */
use Code\Web\Controller;
use Code\Nomad\Receiver;
use Code\Nomad\NomadHandler;
/**
 * @brief Nomad module.
 *
 */
class Nomad extends Controller
{
	public function init()
	{
		$nomad = new Receiver(new NomadHandler());
		json_return_and_die($nomad->run(),'application/x-nomad+json');
	}
}
