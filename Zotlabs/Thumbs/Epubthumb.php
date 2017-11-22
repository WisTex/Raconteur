<?php

namespace Zotlabs\Thumbs;

require_once('library/epub-meta/epub.php');

class Epubthumb {

	function Match($type) {
		return(($type === 'application/epub+zip') ? true : false );
	}

	function Thumb($attach,$preview_style,$height = 300, $width = 300) {

		$photo = false;

		$ep = new \Epub(dbunescbin($attach['content']));
		$data = $ep->Cover();

		if($data['found']) {
			$photo = $data['data'];
		}

        if($photo) {
			$image = imagecreatefromstring($photo);
			$dest = imagecreatetruecolor( $width, $height );
	        $srcwidth = imagesx($image);
    	    $srcheight = imagesy($image);

        	imagealphablending($dest, false);
			imagesavealpha($dest, true);
        	imagecopyresampled($dest, $image, 0, 0, 0, 0, $width, $height, $srcwidth, $srcheight);
            imagedestroy($image);
			imagejpeg($dest,dbunescbin($attach['content']) . '.thumb');
		}
	}
}

