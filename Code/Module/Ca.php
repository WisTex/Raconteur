<?php

/**
 *
 */

namespace Code\Module;

use Code\Web\Controller;
use Code\Storage\Stdio;

/**
 * Ca
 */
class Ca extends Controller
{

    /**
     * get
     *
     * @return void
     */
    public function get()
    {
        if (argc() > 1) {
            $path = 'cache/img/' . substr(argv(1), 0, 2) . '/' . argv(1);

            if (file_exists($path) && filesize($path)) {
                $x = @getimagesize($path);
                if ($x) {
                    header('Content-Type: ' . $x['mime']);
                }

                $cache = intval(get_config('system', 'photo_cache_time'));
                if (!$cache) {
                    $cache = (3600 * 24); // 1 day
                }
                header(
                    'Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache)
                    . ' GMT'
                );
                // Set browser cache age as $cache.  But set timeout of
                // 'shared caches' much lower in the event that infrastructure
                // caching is present.
                $smaxage = intval($cache / 12);
                header(
                    'Cache-Control: s-maxage=' . $smaxage
                    . '; max-age=' . $cache . ';'
                );

                Stdio::fpipe($path,'php://output');
                killme();
            }

            if ($_GET['url']) {
                goaway($url);
            }
        }
        http_status_exit(404, 'Not found');
    }
}
