<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\Channel;

class Online extends Controller
{

    public function init()
    {
        $ret = ['result' => false];
        if (argc() != 2) {
            json_return_and_die($ret);
        }
        json_return_and_die(Channel::get_online_status(argv(1)));
    }
}
