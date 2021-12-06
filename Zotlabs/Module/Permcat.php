<?php

namespace Zotlabs\Module;

use Zotlabs\Lib as Zlib;
use Zotlabs\Web\Controller;

class Permcat extends Controller
{

    private $permcats = [];

    public function init()
    {
        if (! local_channel()) {
            return;
        }

        $permcat = new Zlib\Permcat(local_channel());

        if (argc() > 1) {
            json_return_and_die($permcat->fetch(argv(1)));
        }

        json_return_and_die($permcat->listing());
    }
}
