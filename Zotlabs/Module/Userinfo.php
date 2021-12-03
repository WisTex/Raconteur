<?php

namespace Zotlabs\Module;

use DBA;
use Zotlabs\Web\Controller;
use Zotlabs\Identity\OAuth2Storage;
use Zotlabs\Identity\OAuth2Server;
use OAuth2\Request;

class Userinfo extends Controller
{

    public function init()
    {
        $s = new OAuth2Server(new OAuth2Storage(DBA::$dba->db));
        $request = Request::createFromGlobals();
        $s->handleUserInfoRequest($request)->send();
        killme();
    }
}
