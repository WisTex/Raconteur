<?php

namespace Code\Module;

use DBA;
use Code\Web\Controller;
use Code\Identity\OAuth2Storage;
use Code\Identity\OAuth2Server;
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
