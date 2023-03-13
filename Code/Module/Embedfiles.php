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


        /*
       
              $channel = Channel::from_id($channel_id);
        $p = photos_albums_list($channel, App::get_observer());
        if ($p['success']) {
            return $p['albums'];
        } else {
            return null;
        }
            
        if (argc() > 1 && argv(1) === 'photolink') {
            // API: /embedphotos/photolink
            $href = (x($_POST, 'href') ? $_POST['href'] : null);
            if (!$href) {
                json_return_and_die(['errormsg' => 'Error retrieving link ' . $href, 'status' => false]);
            }
            $tmp = explode('/', $href);
            $resource_id = array_pop($tmp);

            $x = self::photolink($resource_id, $channel_id);
            if ($x) {
                json_return_and_die(['status' => true, 'photolink' => $x, 'resource_id' => $resource_id]);
            }
            json_return_and_die(['errormsg' => 'Error retrieving resource ' . $resource_id, 'status' => false]);
        }
    }

*/
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
            default:
		  		return '';
		}

            $output .= "\n\n" . '[attachment]' . $_POST['hash'] . ',' . $_POST['revision'] . '[/attachment]' . "\n";
            return $output;
        }
        return '';
    }


    /**
     * Copied from include/widgets.php::widget_album() with a modification to get the profile_uid from
     * the input array as in widget_item()
     *
     * @param array $args
     * @return string with HTML
     */

    public function embedphotos_widget_album($args)
    {

        $channel_id = 0;
        if (array_key_exists('channel_id', $args)) {
            $channel_id = $args['channel_id'];
            $channel = Channel::from_id($channel_id);
        }

        if (!$channel_id) {
            return '';
        }

        $owner_uid = $channel_id;
        require_once('include/security.php');
        $sql_extra = permissions_sql($channel_id);

        if (!perm_is_allowed($channel_id, get_observer_hash(), 'view_storage')) {
            return '';
        }

        if ($args['album']) {
            $album = (($args['album'] === '/') ? '' : $args['album']);
        }

        if ($args['title']) {
            $title = $args['title'];
        }

        /**
         * This may return incorrect permissions if you have multiple directories of the same name.
         * It is a limitation of the photo table using a name for a photo album instead of a folder hash
         */
        if ($album) {
            $x = q(
                "select hash from attach where filename = '%s' and uid = %d limit 1",
                dbesc($album),
                intval($owner_uid)
            );
            if ($x) {
                $y = attach_can_view_folder($owner_uid, get_observer_hash(), $x[0]['hash']);
                if (!$y) {
                    return '';
                }
            }
        }

        $order = 'DESC';

        $r = q(
            "SELECT p.resource_id, p.id, p.filename, p.mimetype, p.imgscale, p.description, p.created FROM photo p INNER JOIN
				(SELECT resource_id, max(imgscale) imgscale FROM photo WHERE uid = %d AND album = '%s' AND imgscale <= 4 
				AND photo_usage IN ( %d, %d ) $sql_extra GROUP BY resource_id) ph
				ON (p.resource_id = ph.resource_id AND p.imgscale = ph.imgscale)
				ORDER BY created $order",
            intval($owner_uid),
            dbesc($album),
            intval(PHOTO_NORMAL),
            intval(PHOTO_PROFILE)
        );

        // We will need this to map mimetypes to appropriate file extensions
        $ph = photo_factory('');
        $phototypes = $ph->supportedTypes();

        $photos = [];
        if ($r) {
            $twist = 'rotright';
            foreach ($r as $rr) {
                if ($twist == 'rotright') {
                    $twist = 'rotleft';
                } else {
                    $twist = 'rotright';
                }

                $ext = $phototypes[$rr['mimetype']];

                $imgalt_e = $rr['filename'];
                $desc_e = $rr['description'];

                $imagelink = (z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $rr['resource_id']
                    . (($_GET['order'] === 'posted') ? '?f=&order=posted' : ''));

                $photos[] = [
                    'id' => $rr['id'],
                    'twist' => ' ' . $twist . rand(2, 4),
                    'link' => $imagelink,
                    'title' => t('View Photo'),
                    'src' => z_root() . '/photo/' . $rr['resource_id'] . '-' . $rr['imgscale'] . '.' . $ext,
                    'alt' => $imgalt_e,
                    'desc' => $desc_e,
                    'ext' => $ext,
                    'hash' => $rr['resource_id'],
                    'unknown' => t('Unknown')
                ];
            }
        }

        $o = replace_macros(Theme::get_template('photo_album.tpl'), [
            '$photos' => $photos,
            '$album' => (($title) ? $title : $album),
            '$album_id' => rand(),
            '$album_edit' => [t('Edit Album'), false],
            '$can_post' => false,
            '$upload' => [t('Upload'), z_root() . '/photos/' . $channel['channel_address'] . '/upload/' . bin2hex($album)],
            '$order' => false,
            '$upload_form' => false,
            '$no_fullscreen_btn' => true
        ]);

        return $o;
    }
}
