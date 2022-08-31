<?php
namespace Code\Module;
/**
 * @file Code/Module/Nomad.php
 *
 * @brief Nomad endpoint
 */

use Code\{
    Nomad\NomadHandler,
    Nomad\Receiver,
    Web\Controller,
};

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
