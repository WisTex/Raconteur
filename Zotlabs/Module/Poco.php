<?php
namespace Zotlabs\Module;

/*
 * Portable Contacts server
 */
 

use Zotlabs\Web\Controller;

require_once('include/socgraph.php');

class Poco extends Controller
{

    public function init()
    {
        poco();
    }

}
