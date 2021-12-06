<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libsync;

require_once('include/attach.php');
require_once('include/photos.php');

class Wall_attach extends Controller
{

    public function init()
    {
//      logger('request_method: ' . $_SERVER['REQUEST_METHOD'],LOGGER_DATA,LOG_INFO);
//      logger('wall_attach: ' . print_r($_REQUEST,true),LOGGER_DEBUG,LOG_INFO);
//      logger('wall_attach files: ' . print_r($_FILES,true),LOGGER_DEBUG,LOG_INFO);
        // for testing without actually storing anything
        //      http_status_exit(200,'OK');
    }


    public function post()
    {

        $using_api = false;

        $result = [];

        if ($_REQUEST['api_source'] && array_key_exists('media', $_FILES)) {
            $using_api = true;
        }

        if ($using_api) {
            require_once('include/api.php');
            if (api_user()) {
                $channel = channelx_by_n(api_user());
            }
        } else {
            if (argc() > 1) {
                $channel = channelx_by_nick(argv(1));
            }
        }

        if (!$channel) {
            killme();
        }

        $matches = [];
        $partial = false;

        if (array_key_exists('HTTP_CONTENT_RANGE', $_SERVER)) {
            $pm = preg_match('/bytes (\d*)\-(\d*)\/(\d*)/', $_SERVER['HTTP_CONTENT_RANGE'], $matches);
            if ($pm) {
                // logger('Content-Range: ' . print_r($matches,true));
                $partial = true;
            }
        }

        if ($partial) {
            $x = save_chunk($channel, $matches[1], $matches[2], $matches[3]);
            if ($x['partial']) {
                header('Range: bytes=0-' . (($x['length']) ? $x['length'] - 1 : 0));
                json_return_and_die($x);
            } else {
                header('Range: bytes=0-' . (($x['size']) ? $x['size'] - 1 : 0));

                $_FILES['userfile'] = [
                    'name' => $x['name'],
                    'type' => $x['type'],
                    'tmp_name' => $x['tmp_name'],
                    'error' => $x['error'],
                    'size' => $x['size']
                ];
            }
        } else {
            if (!array_key_exists('userfile', $_FILES)) {
                $_FILES['userfile'] = [
                    'name' => $_FILES['files']['name'],
                    'type' => $_FILES['files']['type'],
                    'tmp_name' => $_FILES['files']['tmp_name'],
                    'error' => $_FILES['files']['error'],
                    'size' => $_FILES['files']['size']
                ];
            }
        }

        $observer = App::get_observer();


        $def_album = get_pconfig($channel['channel_id'], 'system', 'photo_path');
        $def_attach = get_pconfig($channel['channel_id'], 'system', 'attach_path');

        $r = attach_store($channel, (($observer) ? $observer['xchan_hash'] : ''), '', [
            'source' => 'editor',
            'visible' => 0,
            'album' => $def_album,
            'directory' => $def_attach,
            'flags' => 1, // indicates temporary permissions are created
            'allow_cid' => '<' . $channel['channel_hash'] . '>'
        ]);

        if (!$r['success']) {
            notice($r['message'] . EOL);
            killme();
        }

        $s = EMPTY_STR;

        if (intval($r['data']['is_photo'])) {
            $s .= "\n\n" . $r['body'] . "\n\n";
        }

        $url = z_root() . '/cloud/' . $channel['channel_address'] . '/' . $r['data']['display_path'];

        if (strpos($r['data']['filetype'], 'video') === 0) {
            for ($n = 0; $n < 15; $n++) {
                $thumb = Linkinfo::get_video_poster($url);
                if ($thumb) {
                    break;
                }
                sleep(1);
                continue;
            }

            if ($thumb) {
                $s .= "\n\n" . '[zvideo poster=\'' . $thumb . '\']' . $url . '[/zvideo]' . "\n\n";
            } else {
                $s .= "\n\n" . '[zvideo]' . $url . '[/zvideo]' . "\n\n";
            }
        }
        if (strpos($r['data']['filetype'], 'audio') === 0) {
            $s .= "\n\n" . '[zaudio]' . $url . '[/zaudio]' . "\n\n";
        }
        if ($r['data']['filetype'] === 'image/svg+xml') {
            $x = @file_get_contents('store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
            if ($x) {
                $bb = svg2bb($x);
                if ($bb) {
                    $s .= "\n\n" . $bb;
                } else {
                    logger('empty return from svgbb');
                }
            } else {
                logger('unable to read svg data file: ' . 'store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
            }
        }
        if ($r['data']['filetype'] === 'text/vnd.abc' && addon_is_installed('abc')) {
            $x = @file_get_contents('store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
            if ($x) {
                $s .= "\n\n" . '[abc]' . $x . '[/abc]';
            } else {
                logger('unable to read ABC data file: ' . 'store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
            }
        }
        if ($r['data']['filetype'] === 'text/calendar') {
            $content = @file_get_contents('store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
            if ($content) {
                $ev = ical_to_ev($content);
                if ($ev) {
                    $s .= "\n\n" . format_event_bbcode($ev[0]) . "\n\n";
                }
            }
        }

        $s .= "\n\n" . '[attachment]' . $r['data']['hash'] . ',' . $r['data']['revision'] . '[/attachment]' . "\n";


        if ($using_api) {
            return $s;
        }

        $result['message'] = $s;
        json_return_and_die($result);
    }
}
