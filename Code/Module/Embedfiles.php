<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Render\Theme;
use Code\Lib\Addon;

require_once('include/attach.php');
require_once('include/photos.php');

class Embedfiles extends Controller
{
    /** This is the POST destination for the embedfiles button */

    public function post()
    {
        /* start add new */
        $channel = App::get_channel();
        $channel_id = $channel['channel_id'];
        $channel_address = $channel['channel_address'];
        $observer = get_observer_hash();

        if (argc() > 1 && argv(1) === 'sharelink') {
            // API: /embedfiles/sharelink
            $resource_id = $_POST["hash"];

            $x = self::sharelink($resource_id, $channel_id);
            if ($x) {
                json_return_and_die(['status' => true, 'message' => $x, 'resource_id' => $resource_id]);
            }
            json_return_and_die(['errormsg' => 'Error retrieving resource ' . $resource_id, 'status' => false]);
        }

        $orderby = 'is_dir desc';
        $results = attach_list_files($channel_id, $observer, $hash = '', $filename = '', $filetype = '', $orderby, $start = 0, $entries = 0, $since = '', $until = '');
        $success = $results['success'];
        $results = $results['results'];
        $count = count($results);
        $sorted = $this->get_embed_folders($results);
        //$sorted = $this->get_embed_top_folders($results);
        //$sorted = $this->get_embed_sub_folders($results);
        //$sorted = $this->get_embed_files($results);
        json_return_and_die(['success' => $success, 'address' => $channel_address, 'content' => $sorted]);
    }

        /* returns sorted files and folders */
        public function get_embed_folders($results)
        {
            $sorted = array();
            $i = 0;
            foreach ($results as $result) {
                if($result['is_dir'] == 1) {
                    $sorted[$i] = $result;
                    foreach ($results as $result) {
                        if($result['folder'] === $sorted[$i]['hash'] && !$result['is_dir']) {
                            array_push($sorted, $result);
                        }
                    }
                }
                $i = count($sorted);
            }
            return $sorted;
        }

        /* start get_embed_top_folders */
        public function get_embed_top_folders($results)
        {   
            $sorted = array();
            $i = 0;
            foreach ($results as $result) {
                if($result['is_dir'] == 1 && $result['folder'] === '' ) {
                   $sorted[$i] = $result;
                   $i++;
                }
            }
            return $sorted;
        }
        /* end get_embed_top_folders */

        /* start get_embed_sub_folders */
        public function get_embed_sub_folders($results)
        {
            $sorted = array();
            $i = 0;
            foreach ($results as $result) {
                if($result['is_dir'] == 1 && $result['folder'] !== '' ) {
                    $sorted[$i] = $result;
                    $i++;
                }
            }
            return $sorted;
        }
        /* end get_embed_sub_folders */

        /* start get_embed_files */
        public function get_embed_files($results)
        {
            $sorted = array();
            $i = 0;
            foreach ($results as $result) {
                if(!$result['is_dir'] ) {
                   $sorted[$i] = $result;
                   $i++;
                }
            }
            return $sorted;
        }
        /* end get_embed_files */

    protected static function sharelink($resource, $channel_id = 0)
    {
        if (intval($channel_id)) {
            $channel = Channel::from_id($channel_id);
        } else {
            $channel = App::get_channel();
        }

        $output = EMPTY_STR;
        if ($channel) {
            $resolution = 1;
            
        switch($_POST["filetype"]) {
            case "image/jpeg":
            case "image/png":
            case "image/gif":
                if ($_POST["filetype"] === 'image/jpeg') {
                    $ext = '.jpg';
                } elseif ($_POST["filetype"] === 'image/png') {
                    $ext = '.png';
                } elseif ($_POST["filetype"] === 'image/gif') {
                    $ext = '.gif';
                } else {
                    $ext = EMPTY_STR;
                }

                $alt = ' alt="' . $_POST["filename"] . '"';

                $output = '[zrl=' . z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $resource . ']'
                . '[zmg width="100%" height="auto"' . $alt . ']'
                . z_root() . '/photo/' . $resource . '-' . $resolution . $ext . '[/zmg][/zrl]';

                break;
            case "video/mp4":
            case "video/webm":
            case "video/ogg":
                $url = z_root() . '/cloud/' . $channel['channel_address'] . '/' . $_POST['display_path'];
                $output .= "\n\n" . '[zvideo]' . $url . '[/zvideo]' . "\n\n";
                break;
            case "audio/mpeg":
            case "audio/wav":
            case "audio/ogg":
                $url = z_root() . '/cloud/' . $channel['channel_address'] . '/' . $_POST['display_path'];
                $output .= "\n\n" . '[zaudio]' . $url . '[/zaudio]' . "\n\n";
                break;
            case "image/svg+xml":
                $x = @file_get_contents('store/'. $channel['channel_address'] .'/'. $_POST['os_path']);
                if ($x) {
                    $bb = svg2bb($x);
                if ($bb) {
                    $output .= "\n\n" . $bb;
                } else {
                    logger('empty return from svgbb');
                }
            } else {
                logger('unable to read svg data file: '.'store/'. $channel['channel_address'] .'/'. $_POST['os_path']);
            }
                break;
            case "text/vnd.abc":
                if (Addon::is_installed('abc')) {
                    $x = @file_get_contents('store/'. $channel['channel_address'] .'/'. $_POST['os_path']);
                    if ($x) {
                        $output .= "\n\n" . '[abc]' . $x . '[/abc]';
                    } else {
                        logger('unable to read ABC data file: ' . 'store/' . $channel['channel_address'] . '/' . $_POST['os_path']);
                    }
                }
                break;
            case "text/calendar":
                $content = @file_get_contents('store/' . $channel['channel_address'] . '/' . $_POST['os_path']);
                if ($content) {
                    $ev = ical_to_ev($content);
                    if ($ev) {
                        $output .= "\n\n" . format_event_bbcode($ev[0]) . "\n\n";
                    }
                }
                break;
            case "text/x-multicode":
            case "text/bbcode":
            case "text/markdown":
            case "text/html":
            case "text/plain":
            case "application/json":
                $content = @file_get_contents('store/'. $channel['channel_address'] .'/'. $_POST['os_path']);
                    if ($content) {
                        $text = z_input_filter($content, $_POST['filetype']);
                        if ($text) {
                            $output .= "\n\n" . $text . "\n\n";
                        }
                    }
                break;
            default:
		  		return '';
		}
            $output .= "\n\n" . '[attachment]' . $_POST['hash'] . ',' . $_POST['revision'] . '[/attachment]' . "\n";
            return $output;
        }
        return '';
    }
}
