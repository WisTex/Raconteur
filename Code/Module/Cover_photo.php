<?php

namespace Code\Module;

use App;
use Code\Lib\Activity;
use Code\Lib\Libprofile;
use Code\Access\AccessControl;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Extend\Hook;
use Code\Render\Theme;


/*
   @file cover_photo.php
   @brief Module-file with functions for handling of cover-photos

*/

require_once('include/attach.php');
require_once('include/photo_factory.php');
require_once('include/photos.php');



/* @brief Initalize the cover-photo edit view
 *
 * @return void
 *
 */
class Cover_photo extends Controller
{

    public function init()
    {
        if (!local_channel()) {
            return;
        }

        $channel = App::get_channel();
        Libprofile::load($channel['channel_address']);
    }

    /**
     * @brief Evaluate posted values
     *
     * @return void
     *
     */

    public function post()
    {

        if (!local_channel()) {
            return;
        }

        $channel = App::get_channel();

        check_form_security_token_redirectOnErr('/cover_photo', 'cover_photo');

        if ((array_key_exists('cropfinal', $_POST)) && ($_POST['cropfinal'] == 1)) {
            // phase 2 - we have finished cropping

            if (argc() != 2) {
                notice(t('Image uploaded but image cropping failed.') . EOL);
                return;
            }

            $image_id = argv(1);

            if (substr($image_id, -2, 1) == '-') {
                $scale = substr($image_id, -1, 1);
                $image_id = substr($image_id, 0, -2);
            }


            $srcX = intval($_POST['xstart']);
            $srcY = intval($_POST['ystart']);
            $srcW = intval($_POST['xfinal']) - $srcX;
            $srcH = intval($_POST['yfinal']) - $srcY;

            $r = q(
                "select gender from profile where uid = %d and is_default = 1 limit 1",
                intval(local_channel())
            );
            if ($r) {
                $profile = array_shift($r);
            }

            $r = q(
                "SELECT * FROM photo WHERE resource_id = '%s' AND uid = %d AND imgscale > 0 order by imgscale asc LIMIT 1",
                dbesc($image_id),
                intval(local_channel())
            );

            if ($r) {
                $max_thumb = intval(get_config('system', 'max_thumbnail', 1600));
                $iscaled = false;
                if (intval($r[0]['height']) > $max_thumb || intval($r[0]['width']) > $max_thumb) {
                    $imagick_path = get_config('system', 'imagick_convert_path');
                    if ($imagick_path && @file_exists($imagick_path) && intval($r[0]['os_storage'])) {
                        $fname = dbunescbin($r[0]['content']);
                        $tmp_name = $fname . '-001';
                        $newsize = photo_calculate_scale(array_merge(getimagesize($fname), ['max' => $max_thumb]));
                        $cmd = $imagick_path . ' ' . escapeshellarg(PROJECT_BASE . '/' . $fname) . ' -resize ' . $newsize . ' ' . escapeshellarg(PROJECT_BASE . '/' . $tmp_name);
                        //  logger('imagick thumbnail command: ' . $cmd);
                        for ($x = 0; $x < 4; $x++) {
                            exec($cmd);
                            if (file_exists($tmp_name)) {
                                break;
                            }
                        }
                        if (file_exists($tmp_name)) {
                            $base_image = $r[0];
                            $gis = getimagesize($tmp_name);
                            logger('gis: ' . print_r($gis, true));
                            $base_image['width'] = $gis[0];
                            $base_image['height'] = $gis[1];
                            $base_image['content'] = @file_get_contents($tmp_name);
                            $iscaled = true;
                            @unlink($tmp_name);
                        }
                    }
                }
                if (!$iscaled) {
                    $base_image = $r[0];
                    $base_image['content'] = (($base_image['os_storage']) ? @file_get_contents(dbunescbin($base_image['content'])) : dbunescbin($base_image['content']));
                }

                $im = photo_factory($base_image['content'], $base_image['mimetype']);
                if ($im && $im->is_valid()) {
                    // We are scaling and cropping the relative pixel locations to the original photo instead of the
                    // scaled photo we operated on.

                    // First load the scaled photo to check its size. (Should probably pass this in the post form and save
                    // a query.)

                    $g = q(
                        "select width, height from photo where resource_id = '%s' and uid = %d and imgscale = 3",
                        dbesc($image_id),
                        intval(local_channel())
                    );


                    $scaled_width = $g[0]['width'];
                    $scaled_height = $g[0]['height'];

                    if ((!$scaled_width) || (!$scaled_height)) {
                        logger('potential divide by zero scaling cover photo');
                        return;
                    }

                    // unset all other cover photos

                    q(
                        "update photo set photo_usage = %d where photo_usage = %d and uid = %d",
                        intval(PHOTO_NORMAL),
                        intval(PHOTO_COVER),
                        intval(local_channel())
                    );

                    $orig_srcx = ($base_image['width'] / $scaled_width) * $srcX;
                    $orig_srcy = ($base_image['height'] / $scaled_height) * $srcY;
                    $orig_srcw = ($srcW / $scaled_width) * $base_image['width'];
                    $orig_srch = ($srcH / $scaled_height) * $base_image['height'];

                    $im->cropImageRect(1200, 675, $orig_srcx, $orig_srcy, $orig_srcw, $orig_srch);

                    $aid = get_account_id();

                    $p = [
                        'aid' => $aid,
                        'uid' => local_channel(),
                        'resource_id' => $base_image['resource_id'],
                        'filename' => $base_image['filename'],
                        'album' => t('Cover Photos'),
                        'os_path' => $base_image['os_path'],
                        'display_path' => $base_image['display_path'],
                        'created' => $base_image['created'],
                        'edited' => $base_image['edited']
                    ];

                    $p['imgscale'] = 7;
                    $p['photo_usage'] = PHOTO_COVER;

                    $r1 = $im->storeThumbnail($p, PHOTO_RES_COVER_1200);

                    $im->doScaleImage(850, 478);
                    $p['imgscale'] = 8;

                    $r2 = $im->storeThumbnail($p, PHOTO_RES_COVER_850);

                    $im->doScaleImage(425, 239);
                    $p['imgscale'] = 9;

                    $r3 = $im->storeThumbnail($p, PHOTO_RES_COVER_425);

                    if ($r1 === false || $r2 === false || $r3 === false) {
                        // if one failed, delete them all so we can start over.
                        notice(t('Image resize failed.') . EOL);
                        $x = q(
                            "delete from photo where resource_id = '%s' and uid = %d and imgscale >= 7 ",
                            dbesc($base_image['resource_id']),
                            local_channel()
                        );
                        return;
                    }

                    $channel = App::get_channel();
                    $this->send_cover_photo_activity($channel, $base_image, $profile);
                } else {
                    notice(t('Unable to process image') . EOL);
                }
            }

            goaway(z_root() . '/channel/' . $channel['channel_address']);
        }


        $hash = photo_new_resource();
        $smallest = 0;

        $matches = [];
        $partial = false;

        if (array_key_exists('HTTP_CONTENT_RANGE', $_SERVER)) {
            $pm = preg_match('/bytes (\d*)\-(\d*)\/(\d*)/', $_SERVER['HTTP_CONTENT_RANGE'], $matches);
            if ($pm) {
                logger('Content-Range: ' . print_r($matches, true));
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

        $res = attach_store(App::get_channel(), get_observer_hash(), '', array('album' => t('Cover Photos'), 'hash' => $hash));

        logger('attach_store: ' . print_r($res, true), LOGGER_DEBUG);

        json_return_and_die(['message' => $hash]);
    }

    public function send_cover_photo_activity($channel, $photo, $profile)
    {

        $arr = [];
        $arr['item_thread_top'] = 1;
        $arr['item_origin'] = 1;
        $arr['item_wall'] = 1;
        $arr['uuid'] = new_uuid();
        $arr['mid'] = z_root() . '/item/' . $arr['uuid'];
        $arr['obj_type'] = ACTIVITY_OBJ_NOTE;
        $arr['verb'] = ACTIVITY_CREATE;

        if ($profile && stripos($profile['gender'], t('female')) !== false) {
            $t = t('%1$s updated her %2$s');
        } elseif ($profile && stripos($profile['gender'], t('male')) !== false) {
            $t = t('%1$s updated his %2$s');
        } else {
            $t = t('%1$s updated their %2$s');
        }

        $ptext = '[zrl=' . z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $photo['resource_id'] . ']' . t('cover photo') . '[/zrl]';

        $ltext = '[zrl=' . z_root() . '/profile/' . $channel['channel_address'] . ']' . '[zmg alt="' . t('cover photo') . '"]' . z_root() . '/photo/' . $photo['resource_id'] . '-8[/zmg][/zrl]';

        $arr['body'] = sprintf($t, $channel['channel_name'], $ptext) . "\n\n" . $ltext;

        $arr['obj'] = [
            'type' => ACTIVITY_OBJ_NOTE,
            'published' => datetime_convert('UTC', 'UTC', $photo['created'], ATOM_TIME),
            'updated' => datetime_convert('UTC', 'UTC', $photo['edited'], ATOM_TIME),
            'id' => $arr['mid'],
            'url' => ['type' => 'Link', 'mediaType' => $photo['mimetype'], 'href' => z_root() . '/photo/' . $photo['resource_id'] . '-7'],
            'source' => ['content' => $arr['body'], 'mediaType' => 'text/x-multicode'],
            'content' => bbcode($arr['body']),
            'actor' => Activity::encode_person($channel, false),
        ];

        $acl = new AccessControl($channel);
        $x = $acl->get();
        $arr['allow_cid'] = $x['allow_cid'];

        $arr['allow_gid'] = $x['allow_gid'];
        $arr['deny_cid'] = $x['deny_cid'];
        $arr['deny_gid'] = $x['deny_gid'];

        $arr['uid'] = $channel['channel_id'];
        $arr['aid'] = $channel['channel_account_id'];

        $arr['owner_xchan'] = $channel['channel_hash'];
        $arr['author_xchan'] = $channel['channel_hash'];

        post_activity_item($arr);
    }


    /**
     * @brief Generate content of profile-photo view
     *
     * @return string
     *
     */


    public function get()
    {

        if (!local_channel()) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $channel = App::get_channel();

        $newuser = false;

        if (argc() == 2 && argv(1) === 'new') {
            $newuser = true;
        }

        if (argv(1) === 'use') {
            if (argc() < 3) {
                notice(t('Permission denied.') . EOL);
                return;
            }

            //      check_form_security_token_redirectOnErr('/cover_photo', 'cover_photo');

            $resource_id = argv(2);

            $r = q(
                "SELECT id, album, imgscale FROM photo WHERE uid = %d AND resource_id = '%s' and imgscale > 0 ORDER BY imgscale ASC",
                intval(local_channel()),
                dbesc($resource_id)
            );
            if (!$r) {
                notice(t('Photo not available.') . EOL);
                return;
            }
            $havescale = false;
            foreach ($r as $rr) {
                if ($rr['imgscale'] == 7) {
                    $havescale = true;
                }
            }

            $r = q(
                "SELECT content, mimetype, resource_id, os_storage FROM photo WHERE id = %d and uid = %d limit 1",
                intval($r[0]['id']),
                intval(local_channel())
            );
            if (!$r) {
                notice(t('Photo not available.') . EOL);
                return;
            }

            if (intval($r[0]['os_storage'])) {
                $data = @file_get_contents(dbunescbin($r[0]['content']));
            } else {
                $data = dbunescbin($r[0]['content']);
            }

            $ph = photo_factory($data, $r[0]['mimetype']);
            $smallest = 0;
            if ($ph && $ph->is_valid()) {
                // go ahead as if we have just uploaded a new photo to crop
                $i = q(
                    "select resource_id, imgscale from photo where resource_id = '%s' and uid = %d and imgscale = 0",
                    dbesc($r[0]['resource_id']),
                    intval(local_channel())
                );

                if ($i) {
                    $hash = $i[0]['resource_id'];
                    foreach ($i as $ii) {
                        $smallest = intval($ii['imgscale']);
                    }
                }
            }

            $this->cover_photo_crop_ui_head($ph, $hash, $smallest);
        }


        if (!array_key_exists('imagecrop', App::$data)) {
            $o .= replace_macros(Theme::get_template('cover_photo.tpl'), [
                '$user' => App::$channel['channel_address'],
                '$info' => t('Your cover photo may be visible to anybody on the internet'),
                '$existing' => Channel::get_cover_photo(local_channel(), 'array', PHOTO_RES_COVER_850),
                '$lbl_upfile' => t('Upload File:'),
                '$lbl_profiles' => t('Select a profile:'),
                '$title' => t('Change Cover Photo'),
                '$submit' => t('Upload'),
                '$profiles' => $profiles,
                '$embedPhotos' => t('Use a photo from your albums'),
                '$embedPhotosModalTitle' => t('Use a photo from your albums'),
                '$embedPhotosModalCancel' => t('Cancel'),
                '$embedPhotosModalOK' => t('OK'),
                '$modalchooseimages' => t('Choose images to embed'),
                '$modalchoosealbum' => t('Choose an album'),
                '$modaldiffalbum' => t('Choose a different album'),
                '$modalerrorlist' => t('Error getting album list'),
                '$modalerrorlink' => t('Error getting photo link'),
                '$modalerroralbum' => t('Error getting album'),
                '$form_security_token' => get_form_security_token("cover_photo"),
                '$select' => t('Select previously uploaded photo'),

            ]);

            Hook::call('cover_photo_content_end', $o);

            return $o;
        } else {
            $filename = App::$data['imagecrop'] . '-3';
            $resolution = 3;

            $o .= replace_macros(Theme::get_template('cropcover.tpl'), [
                '$filename' => $filename,
                '$profile' => intval($_REQUEST['profile']),
                '$resource' => App::$data['imagecrop'] . '-3',
                '$image_url' => z_root() . '/photo/' . $filename,
                '$title' => t('Crop Image'),
                '$desc' => t('Please adjust the image cropping for optimum viewing.'),
                '$form_security_token' => get_form_security_token("cover_photo"),
                '$done' => t('Done Editing')
            ]);
            return $o;
        }
    }

    /* @brief Generate the UI for photo-cropping
     *
     * @param $a Current application
     * @param $ph Photo-Factory
     * @return void
     *
     */

    public function cover_photo_crop_ui_head($ph, $hash, $smallest)
    {

        $max_length = get_config('system', 'max_image_length', MAX_IMAGE_LENGTH);
        if ($max_length > 0) {
            $ph->scaleImage($max_length);
        }

        $width = $ph->getWidth();
        $height = $ph->getHeight();

        if ($width < 300 || $height < 300) {
            $ph->scaleImageUp(240);
            $width = $ph->getWidth();
            $height = $ph->getHeight();
        }


        App::$data['imagecrop'] = $hash;
        App::$data['imagecrop_resolution'] = $smallest;
        App::$page['htmlhead'] .= replace_macros(Theme::get_template('crophead.tpl'), []);
        return;
    }
}
