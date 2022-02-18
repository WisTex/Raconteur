<?php
namespace Code\Module;

/*
 * Portable Contacts server
 */


use Code\Web\Controller;
use Code\Lib\Socgraph;


class Poco extends Controller
{
    public function init()
    {
        Socgraph::poco();
    }
}
