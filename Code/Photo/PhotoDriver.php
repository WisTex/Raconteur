<?php

namespace Code\Photo;

use App;
use Imagick;
use Code\Lib\Channel;

/**
 * @brief Abstract photo driver class.
 *
 * Inheritance seems not to be the best design pattern for such photo drivers.
 */

abstract class PhotoDriver
{

    /**
     * @brief This variable keeps the image.
     *
     * For GD it is a PHP image resource.
     * For ImageMagick it is an \Imagick object.
     *
     * @var resource|Imagick
     */
    protected $image;

    /**
     * @var int
     */
    protected $width;

    /**
     * @var int
     */
    protected $height;

    /**
     * @var bool
     */
    protected $valid;

    /**
     * @brief The mimetype of the image.
     *
     * @var string
     */
    protected $type;

    /**
     * @brief Supported mimetypes by the used photo driver.
     *
     * @var array
     */
    protected $types;

    /**
     * @brief Return an array with supported mimetypes.
     *
     * @return array
     *  Associative array with mimetype as key and file extension as value.
     */
    abstract public function supportedTypes();

    abstract protected function load($data, $type);

    abstract protected function destroy();

    abstract protected function setDimensions();

    /**
     * @brief Return the current image.
     *
     * @fixme Shouldn't his method be protected, because outside of the current
     * driver it makes no sense at all because of the different return values.
     *
     * @return bool|resource|Imagick
     *  false on failure, a PHP image resource for GD driver, an \Imagick object
     *  for ImageMagick driver.
     */

    abstract public function getImage();

    abstract public function doScaleImage($new_width, $new_height);

    abstract public function rotate($degrees);

    abstract public function flip($horiz = true, $vert = false);

    /**
     * @brief Crops the image.
     *
     * @param int $maxx width of the new image
     * @param int $maxy height of the new image
     * @param int $x x-offset for region
     * @param int $y y-offset for region
     * @param int $w width of region
     * @param int $h height of region
     *
     * @return bool|void false on failure
     */
    abstract public function cropImageRect($maxx, $maxy, $x, $y, $w, $h);

    /**
     * @brief Return a binary string from the image resource.
     *
     * @return string A Binary String.
     */
    abstract public function imageString($animations = true);

    abstract public function clearexif();


    /**
     * @brief PhotoDriver constructor.
     *
     * @param string $data Image
     * @param string $type mimetype
     */
    public function __construct($data, $type = '')
    {
        $this->types = $this->supportedTypes();
        if (! array_key_exists($type, $this->types)) {
            $type = 'image/jpeg';
        }
        $this->type = $type;
        $this->valid = false;
        $this->load($data, $type);
    }

    public function __destruct()
    {
        if ($this->is_valid()) {
            $this->destroy();
        }
    }

    /**
     * @brief Is it a valid image object.
     *
     * @return bool
     */
    public function is_valid()
    {
        return $this->valid;
    }

    /**
     * @brief Get the width of the image.
     *
     * @return bool|number Width of image in pixels, or false on failure
     */
    public function getWidth()
    {
        if (! $this->is_valid()) {
            return false;
        }
        return $this->width;
    }

    /**
     * @brief Get the height of the image.
     *
     * @return bool|number Height of image in pixels, or false on failure
     */
    public function getHeight()
    {
        if (! $this->is_valid()) {
            return false;
        }
        return $this->height;
    }

    /**
     * @brief Saves the image resource to a file in filesystem.
     *
     * @param string $path Path and filename where to save the image
     * @return bool False on failure, otherwise true
     */
    public function saveImage($path, $animated = true)
    {
        if (! $this->is_valid()) {
            return false;
        }
        return (file_put_contents($path, $this->imageString($animated)) ? true : false);
    }

    /**
     * @brief Return mimetype of the image resource.
     *
     * @return bool|string False on failure, otherwise mimetype.
     */
    public function getType()
    {
        if (! $this->is_valid()) {
            return false;
        }
        return $this->type;
    }

    /**
     * @brief Return file extension of the image resource.
     *
     * @return bool|string False on failure, otherwise file extension.
     */
    public function getExt()
    {
        if (! $this->is_valid()) {
            return false;
        }
        return $this->types[$this->getType()];
    }

    /**
     * @brief Scale image to max pixel size in either dimension.
     *
     * @param int $max maximum pixel size in either dimension
     * @param bool $float_height (optional)
     *   If true allow height to float to any length on tall images, constraining
     *   only the width
     * @return bool|void false on failure, otherwise void
     */
    public function scaleImage($max, $float_height = true)
    {
        if (! $this->is_valid()) {
            return false;
        }

        $width  = $this->width;
        $height = $this->height;

        $dest_width = $dest_height = 0;

        if (! ($width && $height)) {
            return false;
        }

        if ($width > $max && $height > $max) {
            // very tall image (greater than 16:9)
            // constrain the width - let the height float.

            if (((($height * 9) / 16) > $width) && ($float_height)) {
                $dest_width = $max;
                $dest_height = intval(($height * $max) / $width);
            } // else constrain both dimensions
            elseif ($width > $height) {
                $dest_width = $max;
                $dest_height = intval(($height * $max) / $width);
            } else {
                $dest_width = intval(($width * $max) / $height);
                $dest_height = $max;
            }
        } else {
            if ($width > $max) {
                $dest_width = $max;
                $dest_height = intval(($height * $max) / $width);
            } else {
                if ($height > $max) {
                    // very tall image (greater than 16:9)
                    // but width is OK - don't do anything

                    if (((($height * 9) / 16) > $width) && ($float_height)) {
                        $dest_width = $width;
                        $dest_height = $height;
                    } else {
                        $dest_width = intval(($width * $max) / $height);
                        $dest_height = $max;
                    }
                } else {
                    $dest_width = $width;
                    $dest_height = $height;
                }
            }
        }
        $this->doScaleImage($dest_width, $dest_height);
    }

    public function scaleImageUp($min)
    {
        if (! $this->is_valid()) {
            return false;
        }

        $width = $this->width;
        $height = $this->height;

        $dest_width = $dest_height = 0;

        if (! ($width && $height)) {
            return false;
        }

        if ($width < $min && $height < $min) {
            if ($width > $height) {
                $dest_width = $min;
                $dest_height = intval(($height * $min) / $width);
            } else {
                $dest_width = intval(($width * $min) / $height);
                $dest_height = $min;
            }
        } else {
            if ($width < $min) {
                $dest_width = $min;
                $dest_height = intval(($height * $min) / $width);
            } else {
                if ($height < $min) {
                    $dest_width = intval(($width * $min) / $height);
                    $dest_height = $min;
                } else {
                    $dest_width = $width;
                    $dest_height = $height;
                }
            }
        }
        $this->doScaleImage($dest_width, $dest_height);
    }

    /**
     * @brief Scales image to a square.
     *
     * @param int $dim Pixel of square image
     * @return bool|void false on failure, otherwise void
     */
    public function scaleImageSquare($dim)
    {
        if (! $this->is_valid()) {
            return false;
        }
        $this->doScaleImage($dim, $dim);
    }

    /**
     * @brief Crops a square image.
     *
     * @see cropImageRect()
     *
     * @param int $max size of the new image
     * @param int $x x-offset for region
     * @param int $y y-offset for region
     * @param int $w width of region
     * @param int $h height of region
     *
     * @return bool|void false on failure
     */
    public function cropImage($max, $x, $y, $w, $h)
    {
        if (! $this->is_valid()) {
            return false;
        }
        $this->cropImageRect($max, $max, $x, $y, $w, $h);
    }

    /**
     * @brief Reads exif data from a given filename.
     *
     * @param string $filename
     * @return bool|array
     */
    public function exif($filename)
    {
        if ((! function_exists('exif_read_data')) || (! in_array($this->getType(), ['image/jpeg', 'image/tiff']))) {
            return false;
        }

        /*
         * PHP 7.2 allows you to use a stream resource, which should reduce/avoid
         * memory exhaustion on large images.
         */

        if (version_compare(PHP_VERSION, '7.2.0') >= 0) {
            $f = @fopen($filename, 'rb');
        } else {
            $f = $filename;
        }

        if ($f) {
            return @exif_read_data($f, null, true);
        }

        return false;
    }

    /**
     * @brief Orients current image based on exif orientation information.
     *
     * @param array $exif
     * @return bool true if oriented, otherwise false
     */
    public function orient($exif)
    {
        if (! ($this->is_valid() && $exif)) {
            return false;
        }

        $ort = ((array_key_exists('IFD0', $exif)) ? $exif['IFD0']['Orientation'] : $exif['Orientation']);

        if (! $ort) {
            return false;
        }

        switch ($ort) {
            case 1: // nothing
                break;
            case 2: // horizontal flip
                $this->flip();
                break;
            case 3: // 180 rotate left
                $this->rotate(180);
                break;
            case 4: // vertical flip
                $this->flip(false, true);
                break;
            case 5: // vertical flip + 90 rotate right
                $this->flip(false, true);
                $this->rotate(-90);
                break;
            case 6: // 90 rotate right
                $this->rotate(-90);
                break;
            case 7: // horizontal flip + 90 rotate right
                $this->flip();
                $this->rotate(-90);
                break;
            case 8: // 90 rotate left
                $this->rotate(90);
                break;
            default:
                break;
        }

        return true;
    }

    /**
     * @brief Save photo to database.
     *
     * @param array $arr
     * @param bool $skipcheck (optional) default false
     * @return bool|array
     */
    public function save($arr, $skipcheck = false)
    {
        if (! ($skipcheck || $this->is_valid())) {
            logger('Attempt to store invalid photo.');
            return false;
        }

        $p = [];

        $p['aid'] = ((intval($arr['aid'])) ? intval($arr['aid']) : 0);
        $p['uid'] = ((intval($arr['uid'])) ? intval($arr['uid']) : 0);
        $p['xchan'] = (($arr['xchan']) ? $arr['xchan'] : '');
        $p['resource_id'] = (($arr['resource_id']) ? $arr['resource_id'] : '');
        $p['filename'] = (($arr['filename']) ? $arr['filename'] : '');
        $p['mimetype'] = (($arr['mimetype']) ? $arr['mimetype'] : $this->getType());
        $p['album'] = (($arr['album']) ? $arr['album'] : '');
        $p['imgscale'] = ((intval($arr['imgscale'])) ? intval($arr['imgscale']) : 0);
        $p['allow_cid'] = (($arr['allow_cid']) ? $arr['allow_cid'] : '');
        $p['allow_gid'] = (($arr['allow_gid']) ? $arr['allow_gid'] : '');
        $p['deny_cid'] = (($arr['deny_cid']) ? $arr['deny_cid'] : '');
        $p['deny_gid'] = (($arr['deny_gid']) ? $arr['deny_gid'] : '');
        $p['edited'] = (($arr['edited']) ? $arr['edited'] : datetime_convert());
        $p['title'] = (($arr['title']) ? $arr['title'] : '');
        $p['description'] = (($arr['description']) ? $arr['description'] : '');
        $p['photo_usage'] = intval($arr['photo_usage']);
        $p['os_storage'] = ((isset($arr['os_storage'])) ? intval($arr['os_storage']) : 1);
        $p['os_path'] = $arr['os_path'];
        $p['os_syspath'] = ((array_key_exists('os_syspath', $arr)) ? $arr['os_syspath'] : '');
        $p['display_path'] = (($arr['display_path']) ? $arr['display_path'] : '');
        $p['width'] = (($arr['width']) ? $arr['width'] : $this->getWidth());
        $p['height'] = (($arr['height']) ? $arr['height'] : $this->getHeight());
        $p['expires'] = (($arr['expires']) ? $arr['expires'] : gmdate('Y-m-d H:i:s', time() + get_config('system', 'photo_cache_time', 86400)));
        $p['profile'] = ((array_key_exists('profile', $arr)) ? intval($arr['profile']) : 0);

        if (! intval($p['imgscale'])) {
            logger('save: ' . print_r($arr, true), LOGGER_DATA);
        }
        $x = q("select id, created from photo where resource_id = '%s' and uid = %d and xchan = '%s' and imgscale = %d limit 1", dbesc($p['resource_id']), intval($p['uid']), dbesc($p['xchan']), intval($p['imgscale']));

        if ($x) {
            $p['created'] = (($x['created']) ? $x['created'] : $p['edited']);
            $r = q(
                "UPDATE photo set
				aid = %d,
				uid = %d,
				xchan = '%s',
				resource_id = '%s',
				created = '%s',
				edited = '%s',
				filename = '%s',
				mimetype = '%s',
				album = '%s',
				height = %d,
				width = %d,
				content = '%s',
				os_storage = %d,
				filesize = %d,
				imgscale = %d,
				photo_usage = %d,
				title = '%s',
				description = '%s',
				os_path = '%s',
				display_path = '%s',
				allow_cid = '%s',
				allow_gid = '%s',
				deny_cid = '%s',
				deny_gid = '%s',
				expires = '%s',
				profile = %d
				where id = %d",
                intval($p['aid']),
                intval($p['uid']),
                dbesc($p['xchan']),
                dbesc($p['resource_id']),
                dbescdate($p['created']),
                dbescdate($p['edited']),
                dbesc(basename($p['filename'])),
                dbesc($p['mimetype']),
                dbesc($p['album']),
                intval($p['height']),
                intval($p['width']),
                (intval($p['os_storage']) ? dbescbin($p['os_syspath']) : dbescbin($this->imageString())),
                intval($p['os_storage']),
                (intval($p['os_storage']) ? @filesize($p['os_syspath']) : strlen($this->imageString())),
                intval($p['imgscale']),
                intval($p['photo_usage']),
                dbesc($p['title']),
                dbesc($p['description']),
                dbesc($p['os_path']),
                dbesc($p['display_path']),
                dbesc($p['allow_cid']),
                dbesc($p['allow_gid']),
                dbesc($p['deny_cid']),
                dbesc($p['deny_gid']),
                dbescdate($p['expires']),
                intval($p['profile']),
                intval($x[0]['id'])
            );
        } else {
            $p['created'] = (($arr['created']) ? $arr['created'] : $p['edited']);
            $r = q("INSERT INTO photo
				( aid, uid, xchan, resource_id, created, edited, filename, mimetype, album, height, width, content, os_storage, filesize, imgscale, photo_usage, title, description, os_path, display_path, allow_cid, allow_gid, deny_cid, deny_gid, expires, profile )
				VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, '%s', %d, %d, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d)", intval($p['aid']), intval($p['uid']), dbesc($p['xchan']), dbesc($p['resource_id']), dbescdate($p['created']), dbescdate($p['edited']), dbesc(basename($p['filename'])), dbesc($p['mimetype']), dbesc($p['album']), intval($p['height']), intval($p['width']), (intval($p['os_storage']) ? dbescbin($p['os_syspath']) : dbescbin($this->imageString())), intval($p['os_storage']), (intval($p['os_storage']) ? @filesize($p['os_syspath']) : strlen($this->imageString())), intval($p['imgscale']), intval($p['photo_usage']), dbesc($p['title']), dbesc($p['description']), dbesc($p['os_path']), dbesc($p['display_path']), dbesc($p['allow_cid']), dbesc($p['allow_gid']), dbesc($p['deny_cid']), dbesc($p['deny_gid']), dbescdate($p['expires']), intval($p['profile']));
        }
        logger('Photo save imgscale ' . $p['imgscale'] . ' returned ' . intval($r));

        return $r;
    }

    /**
     * @brief Stores thumbnail to database or filesystem.
     *
     * @param array $arr
     * @param scale int
     * @return bool|array
     */

    public function storeThumbnail($arr, $scale = 0, $animated = true)
    {

        $arr['imgscale'] = $scale;

        if (boolval(get_config('system', 'filesystem_storage_thumbnails', 1)) && $scale > 0) {
            $channel = Channel::from_id($arr['uid']);
            $arr['os_storage'] = 1;
            $arr['os_syspath'] = 'store/' . $channel['channel_address'] . '/' . $arr['os_path'] . '-' . $scale;
            if (! $this->saveImage($arr['os_syspath'], $animated)) {
                return false;
            }
        }

        if (! $this->save($arr)) {
            if (array_key_exists('os_syspath', $arr)) {
                @unlink($arr['os_syspath']);
            }
            return false;
        }

        return true;
    }
}
