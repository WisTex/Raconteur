<?php

namespace Code\Module;

use App;
use Code\Lib\Addon;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Render\Theme;


require_once('include/attach.php');
require_once('include/photos.php');

class Embedphotos extends Controller
{

    /**
     *
     * This is the POST destination for the embedphotos button
     *
     */
    public function post()
    {
        $channel_id = local_channel();

        if (argc() > 1 && argv(1) === 'album') {
            // API: /embedphotos/album
            $name = (x($_POST, 'name') ? $_POST['name'] : null);
            if (!$name) {
                json_return_and_die(['errormsg' => 'Error retrieving album', 'status' => false]);
            }
            $album = embedfolder_widget(['channel_id' => $channel_id, 'album' => $name]);
            json_return_and_die(['status' => true, 'content' => $album]);
        }
        if (argc() > 1 && argv(1) === 'albumlist') {
            // API: /embedphotos/albumlist
            $album_list = $this->embedphotos_album_list($channel_id);
            json_return_and_die(['status' => true, 'albumlist' => $album_list]);
        }
        if (argc() > 1 && argv(1) === 'photolink') {
            // API: /embedphotos/photolink
            $resource_id = argv(2);
            if (!$resource_id) {
                json_return_and_die(['errormsg' => 'Error retrieving link ' . (string) $resource_id, 'status' => false]);
            }
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

        $r = attach_by_hash($resource,get_observer_hash());
        if (str_starts_with($r['data']['filetype'], 'video')) {
            for ($n = 0; $n < 15; $n++) {
                $thumb = Linkinfo::get_video_poster($url);
                if ($thumb) {
                    break;
                }
                sleep(1);
            }

            if ($thumb) {
                $s .= "\n\n" . '[zvideo poster=\'' . $thumb . '\']' . $url . '[/zvideo]' . "\n\n";
            } else {
                $s .= "\n\n" . '[zvideo]' . $url . '[/zvideo]' . "\n\n";
            }
        }
        if (str_starts_with($r['data']['filetype'], 'audio')) {
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
        elseif ($r['data']['is_photo']) {
            $s = self::getPhotoBody($r['data'], $channel);
        }
        if ($r['data']['filetype'] === 'text/vnd.abc' && Addon::is_installed('abc')) {
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

        if (in_array($r['data']['filetype'], ['text/x-multicode', 'text/bbcode', 'text/markdown', 'text/html'])) {
            $content = @file_get_contents('store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
            if ($content) {
                $text = z_input_filter($content, $r['data']['filetype']);
                if ($text) {
                    $s .= "\n\n" . $text . "\n\n";
                }
            }
        }

        $s .= "\n\n" . '[attachment]' . $r['data']['hash'] . ',' . $r['data']['revision'] . '[/attachment]' . "\n";
        return $s;
    }

    public static function getPhotoBody($attach,$channel)
    {
        $r = q("select * from photo where resource_id = '%s' and uid = %d order by imgscale",
            dbesc($attach['hash']),
            intval($channel['channel_id'])
        );
        if (!$r) {
            return '';
        }
        $image = $r[1] ?? $r[0];
        $link = '[zrl=' . z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $attach['hash'] . ']' .
            '[zmg alt="' . (($image['description']) ?: $image['filename']) . '"]' . z_root() . '/photo/' . $attach['hash'] . '-' . $image['imgscale'] . '[/zmg][/zrl]' . "\n\n";
        return $link;
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
        $p = attach_dirlist($channel,App::get_observer());
        if ($p['success']) {
            return $p['folders'];
        } else {
            return null;
        }
    }
}
