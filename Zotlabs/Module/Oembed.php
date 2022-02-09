<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib as Zlib;

class Oembed extends Controller
{

    public function init()
    {
        // logger('mod_oembed ' . App::$query_string, LOGGER_ALL);

        if (argc() > 1) {
            if (argv(1) == 'b2h') {
                $url = array("", trim(hex2bin($_REQUEST['url'])));
                echo Zlib\Oembed::replacecb($url);
                killme();
            } elseif (argv(1) == 'h2b') {
                $text = trim(hex2bin($_REQUEST['text']));
                echo Zlib\Oembed::html2bbcode($text);
                killme();
            } else {
                echo "<html><head><base target=\"_blank\" rel=\"nofollow noopener\" /></head><body>";
                $src = base64url_decode(argv(1));
                $j = Zlib\Oembed::fetch_url($src);
                echo $j['html'];
                echo "</body></html>";
            }
        }
        killme();
    }
}
