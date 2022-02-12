<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Extend\Hook;
use Zotlabs\Render\Theme;


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
