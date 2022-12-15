<?php

namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\Activity;

class Apschema extends Controller
{

    public function init()
    {

        $arr = Activity::ap_context();

        header('Content-Type: application/ld+json');
        echo json_encode($arr, JSON_UNESCAPED_SLASHES);
        killme();
    }
}
