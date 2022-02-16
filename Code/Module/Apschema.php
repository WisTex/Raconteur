<?php

namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\Activity;

class Apschema extends Controller
{

    public function init()
    {

        $arr = [
            '@context' => array_merge(['as' => 'https://www.w3.org/ns/activitystreams#'], Activity::ap_schema())
        ];

        header('Content-Type: application/ld+json');
        echo json_encode($arr, JSON_UNESCAPED_SLASHES);
        killme();
    }
}
