<?php

namespace Zotlabs\Thumbs;

require_once 'library/epub-meta/epub.php';

/**
 * @brief Thumbnail creation for epub files.
 *
 */
class Epubthumb {

	/**
	 * @brief Match for application/epub+zip.
	 *
	 * @param string $type MimeType
	 * @return boolean
	 */
	function Match($type) {
		return(($type === 'application/epub+zip') ? true : false );
	}

	/**
	 * @brief
	 *
	 * @param array $attach
	 * @param number $preview_style unused
	 * @param number $height (optional) default 300
	 * @param number $width (optional) default 300
	 */
	function Thumb($attach, $preview_style, $height = 300, $width = 300) {

		$photo = false;

		$ep = new \EPub(dbunescbin($attach['content']));
		$data = $ep->Cover();

		if($data['found']) {
			$photo = $data['data'];
		}

		if($photo) {
			$image = imagecreatefromstring($photo);
			$dest = imagecreatetruecolor($width, $height);
			$srcwidth = imagesx($image);
			$srcheight = imagesy($image);

			imagealphablending($dest, false);
			imagesavealpha($dest, true);
			imagecopyresampled($dest, $image, 0, 0, 0, 0, $width, $height, $srcwidth, $srcheight);
			imagedestroy($image);
			imagejpeg($dest, dbunescbin($attach['content']) . '.thumb');
		}
	}
}

