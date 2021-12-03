<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Nullbox extends Controller
{

    public function init()
    {
        http_status_exit(404, 'Permission Denied');
    }
}
