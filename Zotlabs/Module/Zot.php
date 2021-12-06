<?php

/**
 * @file Zotlabs/Module/Zot.php
 *
 * @brief Zot endpoint.
 *
 */

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Zot6\Receiver;
use Zotlabs\Zot6\Zot6Handler;

/**
 * @brief Zot module.
 *
 */
class Zot extends Controller
{

    public function init()
    {
        $zot = new Receiver(new Zot6Handler());
        json_return_and_die($zot->run(), 'application/x-zot+json');
    }
}
