<?php

namespace Zotlabs\Thumbs;

use \ID3Parser\ID3Parser;

class Mp3audio {

	function Match($type) {
		return(($type === 'audio/mpeg') ? true : false );
	}

	function Thumb($attach,$preview_style,$height = 300, $width = 300) {
		$p = new ID3Parser();

        $id = $p->analyze(dbunescbin($attach['content']));

        $photo = isset($id['id3v2']['APIC'][0]['data']) ? $id['id3v2']['APIC'][0]['data'] : null;
        if(is_null($photo) && isset($id['id3v2']['PIC'][0]['data'])) {
            $photo = $id['id3v2']['PIC'][0]['data'];
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

