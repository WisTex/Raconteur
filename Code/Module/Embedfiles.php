<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Render\Theme;


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
        $observer = get_observer_hash();
        $orderby = 'is_dir desc';
        
        $results = attach_list_files($channel_id, $observer, $hash = '', $filename = '', $filetype = '', $orderby, $start = 0, $entries = 0, $since = '', $until = '');
        $success = $results['success'];
        $results = $results['results'];
        $count = count($results);
        $sorted = $this->sort_embed_files($results);
        json_return_and_die(['success' => $success, 'content' => $sorted]);

    }

        /* at this point should just return the top level folders */
        public function sort_embed_files($results)
        {
            $sorted = array();
            $i = 0;
            foreach ($results as $result) {
                
                if($result->is_dir == "1") {
                    $sorted[$i] = $result;
                }
                $i++;
            }
            return $sorted;
        }
    
        
        /*
       
              $channel = Channel::from_id($channel_id);
        $p = photos_albums_list($channel, App::get_observer());
        if ($p['success']) {
            return $p['albums'];
        } else {
            return null;
        }
    
        



        if (argc() > 1 && argv(1) === 'album') {
            // API: /embedphotos/album
            $name = (x($_POST, 'name') ? $_POST['name'] : null);
            if (!$name) {
                json_return_and_die(['errormsg' => 'Error retrieving album', 'status' => false]);
            }
            $album = $this->embedphotos_widget_album(['channel_id' => $channel_id, 'album' => $name]);
            json_return_and_die(['status' => true, 'content' => $album]);
        }
        if (argc() > 1 && argv(1) === 'albumlist') {
            // API: /embedphotos/albumlist
            $album_list = $this->embedphotos_album_list($channel_id);
            json_return_and_die(['status' => true, 'albumlist' => $album_list]);
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


    protected static function photolink($resource, $channel_id = 0)
    {
        if (intval($channel_id)) {
            $channel = Channel::from_id($channel_id);
        } else {
            $channel = App::get_channel();
        }

        $output = EMPTY_STR;
        if ($channel) {
            $resolution = 1;
            $r = q(
                "select mimetype, height, width, title from photo where resource_id = '%s' and imgscale = %d and uid = %d limit 1",
                dbesc($resource),
                intval($resolution),
                intval($channel['channel_id'])
            );
            if (!$r) {
                return $output;
            }

            if ($r[0]['mimetype'] === 'image/jpeg') {
                $ext = '.jpg';
            } elseif ($r[0]['mimetype'] === 'image/png') {
                $ext = '.png';
            } elseif ($r[0]['mimetype'] === 'image/gif') {
                $ext = '.gif';
            } else {
                $ext = EMPTY_STR;
            }

            $alt = $r[0]['title'];
            if (!$alt) {
                $a = q(
                    "select filename from attach where hash = '%s' and uid = %d limit 1",
                    dbesc($resource),
                    intval($channel['channel_id'])
                );
                if ($a) {
                    $alt = $a[0]['filename'];
                } else {
                    $alt = t('Image/photo');
                }
            }
            $alt = ' alt="' . $alt . '"';

            $output = '[zrl=' . z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $resource . ']'
                . '[zmg width="' . $r[0]['width'] . '" height="' . $r[0]['height'] . '"' . $alt . ']'
                . z_root() . '/photo/' . $resource . '-' . $resolution . $ext . '[/zmg][/zrl]';

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

    public function embedphotos_album_list($channel_id)
    {
        $channel = Channel::from_id($channel_id);
        $p = photos_albums_list($channel, App::get_observer());
        if ($p['success']) {
            return $p['albums'];
        } else {
            return null;
        }
    }
}
