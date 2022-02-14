<?php
namespace Zotlabs\Module;

/*
 * Portable Contacts server
 */


use Zotlabs\Web\Controller;
use Zotlabs\Lib\Socgraph;


class Poco extends Controller
{
    public function init()
    {
        Socgraph::poco();
    }
}
