<?php

use Code\Photo\PhotoDriver;
use Code\Photo\PhotoGd;
use Code\Photo\PhotoImagick;
use Code\Lib\Config;
use Code\Lib\Img_cache;
use Code\Lib\Hashpath;
use Code\Lib\Channel;
use Code\Lib\Url;

/**
 * @brief Return a PhotoDriver object.
 *
 * Use this factory when manipulating images.
 *
 * Return a photo driver object implementing ImageMagick or GD.
 *
 * @param string $data Image data
 * @param string $type Mimetype
 * @return null|PhotoDriver
 *   NULL if unsupported image type or failure, otherwise photo driver object
 */
function photo_factory($data, $type = null)
{
    $ph = null;
    $m = null;

    $unsupported_types = [
        'image/bmp',
        'image/vnd.microsoft.icon',
        'image/tiff',
        'image/svg+xml',
    ];

    if ($type && in_array(strtolower($type), $unsupported_types)) {
        logger('Unsupported image type ' . $type);
        return null;
    }


    $ignore_imagick = get_config('system', 'ignore_imagick');

    if (class_exists('Imagick') && !$ignore_imagick) {
        $ph = new PhotoImagick($data, $type);

        // As of August 2020, webp support is still poor in both imagick and gd. Both claim to support it,
        // but both may require additional configuration. If it's a webp and the imagick driver failed,
        // we'll try again with GD just in case that one handles it. If not, you may need to install libwebp
        // which should make imagick work and/or re-compile php-gd with the option to include that library.

        if (! $ph->is_valid() && $type === 'image/webp') {
            $ph = null;
        }
    }

    if (! $ph) {
        $ph = new PhotoGd($data, $type);
    }

    return $ph;
}


/**
 * @brief Guess image mimetype from filename or from Content-Type header.
 *
 * @param string $filename
 *   Image filename
 * @param string $headers (optional)
 *   Headers to check for Content-Type (from curl request)
 * @return null|string Guessed mimetype
 */

function guess_image_type($filename, $headers = '')
{
    //  logger('Photo: guess_image_type: '.$filename . ($headers?' from curl headers':''), LOGGER_DEBUG);
    $type = null;
    $m = null;

    if ($headers) {
        $hdrs = [];
        $h = explode("\n", $headers);
        foreach ($h as $l) {
            if (strpos($l, ':') !== false) {
                list($k, $v) = array_map('trim', explode(':', trim($l), 2));
                $hdrs[strtolower($k)] = $v;
            }
        }
        logger('Curl headers: ' . print_r($hdrs, true), LOGGER_DEBUG);
        if (array_key_exists('content-type', $hdrs)) {
            $ph = photo_factory('');
            $types = $ph->supportedTypes();

            if (array_key_exists($hdrs['content-type'], $types)) {
                $type = $hdrs['content-type'];
            }
        }
    }

    if (is_null($type)) {
        $ignore_imagick = get_config('system', 'ignore_imagick');
        // Guessing from extension? Isn't that... dangerous?
        if (class_exists('Imagick') && file_exists($filename) && is_readable($filename) && !$ignore_imagick) {
                /**
                 * Well, this not much better,
                 * but at least it comes from the data inside the image,
                 * we won't be tricked by a manipulated extension
                 */
                $image = new Imagick($filename);
                $type = $image->getImageMimeType();
        }

        if (is_null($type)) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $ph = photo_factory('');
            $types = $ph->supportedTypes();
            foreach ($types as $m => $e) {
                if ($ext === $e) {
                    $type = $m;
                }
            }
        }

        if (is_null($type) && (strpos($filename, 'http') === false)) {
            $size = getimagesize($filename);
            $ph = photo_factory('');
            $types = $ph->supportedTypes();
            $type = ((array_key_exists($size['mime'], $types)) ? $size['mime'] : 'image/jpeg');
        }
        if (is_null($type)) {
            if (strpos(strtolower($filename), 'jpg') !== false) {
                $type = 'image/jpeg';
            } elseif (strpos(strtolower($filename), 'jpeg') !== false) {
                $type = 'image/jpeg';
            } elseif (strpos(strtolower($filename), 'gif') !== false) {
                $type = 'image/gif';
            } elseif (strpos(strtolower($filename), 'png') !== false) {
                $type = 'image/png';
            }
        }
    }

    logger('Photo: guess_image_type: filename = ' . $filename . ' type = ' . $type, LOGGER_DEBUG);

    return $type;
}

/**
 * @brief Delete thing photo from database.
 *
 * @param string $url
 * @param string $ob_hash
 * @return void
 */
function delete_thing_photo($url, $ob_hash)
{

    $hash = basename($url);
    $dbhash = substr($hash, 0, strpos($hash, '-'));

    // hashes should be 32 bytes.

    if ((! $ob_hash) || (strlen($dbhash) < 16)) {
        return;
    }

    if (strpos($url, '/xp/') !== false && strpos($url, '.obj') !== false) {
        $xppath = 'cache/xp/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        if (file_exists($xppath)) {
            unlink($xppath);
        }
        $xppath = str_replace('-4', '-5', $xppath);
        if (file_exists($xppath)) {
            unlink($xppath);
        }
        $xppath = str_replace('-5', '-6', $xppath);
        if (file_exists($xppath)) {
            unlink($xppath);
        }
    } else {
        q(
            "delete from photo where xchan = '%s' and photo_usage = %d and resource_id = '%s'",
            dbesc($ob_hash),
            intval(PHOTO_THING),
            dbesc($dbhash)
        );
    }
}

/**
 * @brief Fetches a photo from external site and prepares its miniatures.
 *
 * @param string $src
 *    external URL to fetch base image
 * @param string $xchan
 *    channel unique hash
 * @param bool $thing
 *    TRUE if this is a thing URL
 *
 * @return array of results
 * * \e string \b 0 => local URL to full image
 * * \e string \b 1 => local URL to standard thumbnail
 * * \e string \b 2 => local URL to micro thumbnail
 * * \e string \b 3 => image type
 * * \e boolean \b 4 => TRUE if fetch failure
 */
function import_remote_xchan_photo($src, $xchan, $thing = false)
{

    $failure  = [];
    $type = EMPTY_STR;

    logger(sprintf('importing %s for %s', $src, $xchan), LOGGER_DEBUG);
    
    $animated = get_config('system', 'animated_avatars', true);

    $path = Hashpath::path((($thing) ? $src . $xchan : $xchan), 'cache/xp', 2);
    $hash = basename($path);

    $cached_file = $path . '-4' . (($thing) ? '.obj' : EMPTY_STR);

    // Maybe it's already a cached xchan photo on our site. Do nothing.

    if (strpos($src, z_root() . '/xp/') === 0) {
        return false;
    }

    if (file_exists($cached_file)) {
        $info = getimagesize($cached_file);
        if (isset($info) && is_array($info) && array_key_exists('mime', $info)) {
            $type = $info['mime'];
        }
    }
    else {
        $type = 'image/png';
    }

    // Always return these paths. The Xp module will return the default profile photo if unset.
    
    $photo = z_root() . '/xp/' . $hash . '-4' . (($thing) ? '.obj' : EMPTY_STR);
    $thumb = z_root() . '/xp/' . $hash . '-5' . (($thing) ? '.obj' : EMPTY_STR);
    $micro = z_root() . '/xp/' . $hash . '-6' . (($thing) ? '.obj' : EMPTY_STR);

    $result = Url::get($src);
    
    if ($result['success']) {
        $type = guess_image_type($src, $result['header']);
        if ((! $type) || strpos($type, 'image') === false) {
            logger('fetching type from file', LOGGER_DEBUG);
            @file_put_contents('cache/' . $hash, $result['body']);
            $info = getimagesize('cache/' . $hash);
            @unlink('cache/' . $hash);
            if (isset($info) && is_array($info) && array_key_exists('mime', $info)) {
                $type = $info['mime'];
            }
        }
        if ($type) {
            $img = photo_factory($result['body'], $type);
            if ($img && $img->is_valid()) {
                $width = $img->getWidth();
                $height = $img->getHeight();

                if ($width && $height) {
                    if (($width / $height) > 1.2) {
                        // crop out the sides
                        $margin = $width - $height;
                        $img->cropImage(300, ($margin / 2), 0, $height, $height);
                    } elseif (($height / $width) > 1.2) {
                        // crop out the bottom
                        $margin = $height - $width;
                        $img->cropImage(300, 0, 0, $width, $width);
                    } else {
                        $img->scaleImageSquare(300);
                    }
                } else {
                    $failure[] = 'No dimensions';
                }
                $savepath = $path . '-4' . (($thing) ? '.obj' : EMPTY_STR);
                $r = $img->saveImage($savepath, $animated);
                if ($r === false) {
                    $failure[] = 'Storage failure size 4';
                }
                $img->scaleImage(80);
                $savepath = $path . '-5' . (($thing) ? '.obj' : EMPTY_STR);
                $r = $img->saveImage($savepath, $animated);
                if ($r === false) {
                    $failure[] = 'Storage failure size 5';
                }
                $img->scaleImage(48);
                $savepath = $path . '-6' . (($thing) ? '.obj' : EMPTY_STR);
                $r = $img->saveImage($savepath, $animated);
                if ($r === false) {
                    $failure[] = 'Storage failure size 6';
                }
            }
            else {
                $failure[] = 'Invalid image from ' . $src;
            }
        }
        else {
            $failure[] = 'unknown filetype';
        }
    } else {
        $failure[] = 'Unable to fetch ' . $src;
        $failure[] = $result['error'];
        $failure[] = print_array($result['debug']);
    }
    
    if ($failure) {
        logger('failed: ' . $photo);
        logger('failure: ' . print_r($failure,true), LOGGER_DEBUG);
        file_put_contents($path . '.log', implode("\n", $failure));
    } elseif (file_exists($path . '.log')) {
        unlink($path . '.log');
    }

    logger('cached photo: ' . $photo, LOGGER_DEBUG);    
    return([$photo, $thumb, $micro, $type, ($failure ? true : false)]);
}



function import_remote_cover_photo($src, $xchan)
{

    $failure  = [];
    $type = EMPTY_STR;

    if (!Config::Get('system','remote_cover_photos')) {
        return false;
    }

    logger(sprintf('importing %s for %s', $src, $xchan), LOGGER_DEBUG);

    $path = Hashpath::path($xchan, 'cache/xp', 2);
    $hash = basename($path);

    // Maybe it's already a cached xchan photo on our site. Do nothing.

    if (strpos($src, z_root() . '/xp/') === 0) {
        return false;
    }

    $orig = $path . '.orig';

    $fp = fopen($orig, 'wb');
    if (!$fp) {
        logger('failed to create storage file.', LOGGER_NORMAL);
        return false;
    }
    $result = Url::get($src, ['filep' => $fp]);
    fclose($fp);

    if ($result['success']) {
        $info = getimagesize($orig);
        if (!$info) {
            logger('storage failed.');
            return false;
        }
        $type = $info['mime'];
        $imagick_path = Config::Get('system','imagick_convert_path');
        if ($imagick_path && file_exists($imagick_path)) {
            exec($imagick_path . ' '
                . escapeshellarg(PROJECT_BASE . '/' . $orig)
                . ' -resize 425x139 '
                . escapeshellarg(PROJECT_BASE . '/' . $path . '-9')
            );
        }
    }
    unlink($orig);
    return file_exists($path . '-9');
}

/**
  * @brief Import channel photo from a URL.
 *
 * @param string $photo URL to a photo
 * @param int $aid
 * @param int $uid channel_id
 * @return null|string Guessed image mimetype or null.
 */
function import_channel_photo_from_url($photo, $aid, $uid)
{
    $type = null;

    if ($photo) {
        $result = Url::get($photo);

        if ($result['success']) {
            $img_str = $result['body'];
            $type = guess_image_type($photo, $result['header']);

            import_channel_photo($img_str, $type, $aid, $uid);
        }
    }

    return $type;
}

/**
 * @brief Import a channel photo and prepare its miniatures.
 *
 * @param string $photo Image data
 * @param string $type
 * @param int $aid
 * @param int $uid channel_id
 * @return bool|string false on failure, otherwise resource_id of photo
 */
function import_channel_photo($photo, $type, $aid, $uid)
{

    logger('Importing channel photo for ' . $uid, LOGGER_DEBUG);

    $r = q(
        "select resource_id from photo where uid = %d and photo_usage = 1 and imgscale = 4",
        intval($uid)
    );
    if ($r) {
        $hash = $r[0]['resource_id'];
    } else {
        $hash = photo_new_resource();
    }

    $photo_failure = false;
    $filename = $hash;

    $img = photo_factory($photo, $type);
    if ($img->is_valid()) {
        // config array for image save method
        $p = [
            'aid'         => $aid,
            'uid'         => $uid,
            'resource_id' => $hash,
            'filename'    => $filename,
            'album'       => t('Profile Photos'),
            'photo_usage' => PHOTO_PROFILE,
            'imgscale'    => PHOTO_RES_PROFILE_300,
        ];

        // photo size
        $img->scaleImageSquare(300);
        $r = $img->save($p);
        if ($r === false) {
            $photo_failure = true;
        }

        // thumb size
        $img->scaleImage(80);
        $p['imgscale'] = 5;
        $r = $img->save($p);
        if ($r === false) {
            $photo_failure = true;
        }

        // micro size
        $img->scaleImage(48);
        $p['imgscale'] = 6;
        $r = $img->save($p);
        if ($r === false) {
            $photo_failure = true;
        }
    } else {
        logger('Invalid image.');
        $photo_failure = true;
    }

    if ($photo_failure) {
        return false;
    }

    return $hash;
}
