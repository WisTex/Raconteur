<?php

namespace Code\Module;

use Code\Lib as Zlib;
use Code\Web\Controller;

class Permcat extends Controller
{

    private $permcats = [];

    public function init()
    {

        if (! (local_channel() && Zlib\Apps::system_app_installed(local_channel(),'Roles'))) {
            json_return_and_die([ 'success' => false ]);
        }
    
        $abook_id = (argc() > 2) ? argv(2) : EMPTY_STR;
        $permcat = new Zlib\Permcat(local_channel(), $abook_id);

        if (argc() > 1) {
            // logger('fetched ' . argv(1) . ':' . print_r($permcat->fetch(argv(1)),true));
            json_return_and_die($permcat->fetch(argv(1)));
        }

        json_return_and_die($permcat->listing());
    }
}
