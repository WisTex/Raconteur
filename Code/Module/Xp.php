<?php
namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Storage\Stdio;

class Xp extends Controller
{

    public function get()
    {
        if (argc() > 1) {
            $path = 'cache/xp/' . substr(argv(1), 0, 2) . '/' . substr(argv(1), 2, 2) . '/' . argv(1);

            if (!file_exists($path)) {
                // no longer cached for some reason, perhaps expired
                $resolution = substr(argv(1), (-2), 2);
                if ($resolution && substr($resolution, 0, 1) === '-') {
                    switch (substr($resolution, 1, 1)) {
                        case '4':
                            $path = Channel::get_default_profile_photo();
                            break;
                        case '5':
                            $path = Channel::get_default_profile_photo(80);
                            break;
                        case '6':
                            $path = Channel::get_default_profile_photo(48);
                            break;
                        default:
                            break;
                    }
                }
            }

            if (!file_exists($path)) {
                http_status_exit(404, 'Not found');
            }

            $x = @getimagesize($path);
            if ($x) {
                header('Content-Type: ' . $x['mime']);
            }

            $cache = intval(get_config('system', 'photo_cache_time'));
            if (!$cache) {
                $cache = (3600 * 24); // 1 day
            }
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache) . ' GMT');
            // Set browser cache age as $cache.  But set timeout of 'shared caches'
            // much lower in the event that infrastructure caching is present.
            $smaxage = intval($cache / 12);
            header('Cache-Control: s-maxage=' . $smaxage . '; max-age=' . $cache . ';');

            Stdio::fpipe($path,'php://output');
            killme();
        }

        http_status_exit(404, 'Not found');
    }
}
