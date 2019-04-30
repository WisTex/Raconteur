<?php

use Zotlabs\Photo\PhotoDriver;
use Zotlabs\Photo\PhotoGd;
use Zotlabs\Photo\PhotoImagick;

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
function photo_factory($data, $type = null) {
	$ph = null;
	$m = null;

	$unsupported_types = [
		'image/bmp',
		'image/vnd.microsoft.icon',
		'image/tiff',
		'image/svg+xml',
	];

	if($type && in_array(strtolower($type), $unsupported_types)) {
		logger('Unsupported image type ' . $type);
		return null;
	}

	$ignore_imagick = get_config('system', 'ignore_imagick');

	if(class_exists('Imagick') && !$ignore_imagick) {
		$v = Imagick::getVersion();
		preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $v['versionString'], $m);
		if(version_compare($m[1], '6.6.7') >= 0) {
			$ph = new PhotoImagick($data, $type);
		} else {
			// earlier imagick versions have issues with scaling png's
			// don't log this because it will just fill the logfile.
			// leave this note here so those who are looking for why
			// we aren't using imagick can find it
		}
	}

	if(! $ph) {
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
function guess_image_type($filename, $headers = '') {
//	logger('Photo: guess_image_type: '.$filename . ($headers?' from curl headers':''), LOGGER_DEBUG);
	$type = null;
	$m = null;

	if($headers) {
		$hdrs = [];
		$h = explode("\n", $headers);
		foreach ($h as $l) {
			list($k, $v) = array_map('trim', explode(':', trim($l), 2));
			$hdrs[strtolower($k)] = $v;
		}
		logger('Curl headers: ' .var_export($hdrs, true), LOGGER_DEBUG);
		if(array_key_exists('content-type', $hdrs)) {
			$ph = photo_factory('');
			$types = $ph->supportedTypes();

			if(array_key_exists($hdrs['content-type'], $types))
				$type = $hdrs['content-type'];
		}
	}

	if(is_null($type)){
		$ignore_imagick = get_config('system', 'ignore_imagick');
		// Guessing from extension? Isn't that... dangerous?
		if(class_exists('Imagick') && file_exists($filename) && is_readable($filename) && !$ignore_imagick) {
			$v = Imagick::getVersion();
			preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', $v['versionString'], $m);
			if(version_compare($m[1], '6.6.7') >= 0) {
				/**
				 * Well, this not much better,
				 * but at least it comes from the data inside the image,
				 * we won't be tricked by a manipulated extension
			 	*/
				$image = new Imagick($filename);
				$type = $image->getImageMimeType();
			}
			else {
				// earlier imagick versions have issues with scaling png's
				// don't log this because it will just fill the logfile.
				// leave this note here so those who are looking for why
				// we aren't using imagick can find it
			}
		}

		if(is_null($type)) {
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			$ph = photo_factory('');
			$types = $ph->supportedTypes();
			foreach($types as $m => $e) {
				if($ext === $e) {
					$type = $m;
				}
			}
		}

		if(is_null($type) && (strpos($filename, 'http') === false)) {
			$size = getimagesize($filename);
			$ph = photo_factory('');
			$types = $ph->supportedTypes();
			$type = ((array_key_exists($size['mime'], $types)) ? $size['mime'] : 'image/jpeg');
		}
		if(is_null($type)) {
			if(strpos(strtolower($filename),'jpg') !== false)
				$type = 'image/jpeg';
			elseif(strpos(strtolower($filename),'jpeg') !== false)
				$type = 'image/jpeg';
			elseif(strpos(strtolower($filename),'gif') !== false)
				$type = 'image/gif';
			elseif(strpos(strtolower($filename),'png') !== false)
				$type = 'image/png';
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
function delete_thing_photo($url, $ob_hash) {

	$hash = basename($url);
	$hash = substr($hash, 0, strpos($hash, '-'));

	// hashes should be 32 bytes.

	if((! $ob_hash) || (strlen($hash) < 16))
		return;

	q("delete from photo where xchan = '%s' and photo_usage = %d and resource_id = '%s'",
		dbesc($ob_hash),
		intval(PHOTO_THING),
		dbesc($hash)
	);
}

/**
 * @brief Fetches a photo from external site and prepares its miniatures.
 *
 * @param string $photo
 *    external URL to fetch base image
 * @param string $xchan
 *    channel unique hash
 * @param boolean $thing
 *    TRUE if this is a thing URL
 * @param boolean $force
 *    TRUE if ignore image modification date check (force fetch)
 *
 * @return array of results
 * * \e string \b 0 => local URL to full image
 * * \e string \b 1 => local URL to standard thumbnail
 * * \e string \b 2 => local URL to micro thumbnail
 * * \e string \b 3 => image type
 * * \e boolean \b 4 => TRUE if fetch failure
 * * \e string \b 5 => modification date
 */
function import_xchan_photo($photo, $xchan, $thing = false, $force = false) {
	$modified = '';
	$o = null;

	$flags = (($thing) ? PHOTO_THING : PHOTO_XCHAN);
	$album = (($thing) ? 'Things' : 'Contact Photos');

	logger('Updating channel photo from ' . $photo . ' for ' . $xchan, LOGGER_DEBUG);

	if($thing) {
		$hash = photo_new_resource();
	} else {
		$r = q("select resource_id, edited, mimetype from photo where xchan = '%s' and photo_usage = %d and imgscale = 4 limit 1", dbesc($xchan), intval(PHOTO_XCHAN));
		if($r) {
			$hash = $r[0]['resource_id'];
			$modified = $r[0]['edited'];
			$type = $r[0]['mimetype'];
		} else {
			$hash = photo_new_resource();
		}
	}

	$photo_failure = false;
	$img_str = '';

	if($photo) {

		if($force || $modified == '') {
			$result = z_fetch_url($photo, true);
		} else {
			$h = [
					'headers' => [
							'If-Modified-Since: ' . gmdate('D, d M Y H:i:s', strtotime($modified . 'Z')) . ' GMT'
					]
			];
			$result = z_fetch_url($photo, true, 0, $h);
		}

		if($result['success']) {
			$img_str = $result['body'];
			$type = guess_image_type($photo, $result['header']);
			$modified = gmdate('Y-m-d H:i:s', (preg_match('/last-modified: (.+) \S+/i', $result['header'], $o) ? strtotime($o[1] . 'Z') : time()));
			if(is_null($type))
				$photo_failure = true;
		} elseif($result['return_code'] == 304) {
			$photo = z_root() . '/photo/' . $hash . '-4';
			$thumb = z_root() . '/photo/' . $hash . '-5';
			$micro = z_root() . '/photo/' . $hash . '-6';
		} else {
			$photo_failure = true;
		}
	} else {
		$photo_failure = true;
	}

	if(!$photo_failure && $result['return_code'] != 304) {
		$img = photo_factory($img_str, $type);
		if($img->is_valid()) {
			$width = $img->getWidth();
			$height = $img->getHeight();

			if($width && $height) {
				if(($width / $height) > 1.2) {
					// crop out the sides
					$margin = $width - $height;
					$img->cropImage(300, ($margin / 2), 0, $height, $height);
				} elseif(($height / $width) > 1.2) {
					// crop out the bottom
					$margin = $height - $width;
					$img->cropImage(300, 0, 0, $width, $width);
				} else {
					$img->scaleImageSquare(300);
				}
			} else {
				$photo_failure = true;
			}

			$p = [
					'xchan' => $xchan,
					'resource_id' => $hash,
					'filename' => basename($photo),
					'album' => $album,
					'photo_usage' => $flags,
					'imgscale' => 4,
					'edited' => $modified,
			];

			$r = $img->save($p);
			if($r === false)
				$photo_failure = true;

			$img->scaleImage(80);
			$p['imgscale'] = 5;
			$r = $img->save($p);
			if($r === false)
				$photo_failure = true;

			$img->scaleImage(48);
			$p['imgscale'] = 6;
			$r = $img->save($p);
			if($r === false)
				$photo_failure = true;

			$photo = z_root() . '/photo/' . $hash . '-4';
			$thumb = z_root() . '/photo/' . $hash . '-5';
			$micro = z_root() . '/photo/' . $hash . '-6';
		} else {
			logger('Invalid image from ' . $photo);
			$photo_failure = true;
		}
	}
	if($photo_failure) {
		$default = get_default_profile_photo();
		$photo = z_root() . '/' . $default;
		$thumb = z_root() . '/' . get_default_profile_photo(80);
		$micro = z_root() . '/' . get_default_profile_photo(48);
		$type = 'image/png';
		$modified = gmdate('Y-m-d H:i:s', filemtime($default));
	}

	logger('HTTP code: ' . $result['return_code'] . '; modified: ' . $modified
			. '; failure: ' . ($photo_failure ? 'yes' : 'no') . '; URL: ' . $photo, LOGGER_DEBUG);

	return([$photo, $thumb, $micro, $type, $photo_failure, $modified]);
}

/**
  * @brief Import channel photo from a URL.
 *
 * @param string $photo URL to a photo
 * @param int $aid
 * @param int $uid channel_id
 * @return null|string Guessed image mimetype or null.
 */
function import_channel_photo_from_url($photo, $aid, $uid) {
	$type = null;

	if($photo) {
		$result = z_fetch_url($photo, true);

		if($result['success']) {
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
 * @return boolean|string false on failure, otherwise resource_id of photo
 */
function import_channel_photo($photo, $type, $aid, $uid) {

	logger('Importing channel photo for ' . $uid, LOGGER_DEBUG);

	$photo_failure = false;
	$hash = photo_new_resource();
	$filename = $hash;

	$img = photo_factory($photo, $type);
	if($img->is_valid()) {

		// config array for image save method
		$p = [
				'aid' => $aid,
				'uid' => $uid,
				'resource_id' => $hash,
				'filename' => $filename,
				'album' => t('Profile Photos'),
				'photo_usage' => PHOTO_PROFILE,
				'imgscale' => 4,
		];

		// photo size
		$img->scaleImageSquare(300);
		$r = $img->save($p);
		if($r === false)
			$photo_failure = true;

		// thumb size
		$img->scaleImage(80);
		$p['imgscale'] = 5;
		$r = $img->save($p);
		if($r === false)
			$photo_failure = true;

		// micro size
		$img->scaleImage(48);
		$p['imgscale'] = 6;
		$r = $img->save($p);
		if($r === false)
			$photo_failure = true;

	} else {
		logger('Invalid image.');
		$photo_failure = true;
	}

	if($photo_failure)
		return false;
	else
		return $hash;
}
