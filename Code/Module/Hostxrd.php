<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Extend\Hook;
use Code\Render\Theme;


class Hostxrd extends Controller
{

    public function init()
    {
        session_write_close();
        header('Access-Control-Allow-Origin: *');
        header("Content-type: application/xrd+xml");
        logger('hostxrd', LOGGER_DEBUG);

        $tpl = Theme::get_template('xrd_host.tpl');
        $x = replace_macros(Theme::get_template('xrd_host.tpl'), array(
            '$zhost' => App::get_hostname(),
            '$zroot' => z_root()
        ));
        $arr = array('xrd' => $x);
        Hook::call('hostxrd', $arr);

        echo $arr['xrd'];
        killme();
    }
}
