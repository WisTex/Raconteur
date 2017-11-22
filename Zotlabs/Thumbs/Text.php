<?php

namespace Zotlabs\Thumbs;


class Text {

	function MatchDefault($type) {
		return(($type === 'text') ? true : false );
	}

	function Thumb($attach,$preview_style,$height = 300, $width = 300) {

		$stream = @fopen(dbunescbin($attach['content']),'rb');
		if($stream) {
			$content = trim(stream_get_contents($stream,4096));
			$content = str_replace("\r",'',$content);
			$content_a = explode("\n",$content);
		}
		if($content_a) {
			$fsize = 4;
			$lsize = 8;
			$image = imagecreate($width,$height);
			imagecolorallocate($image,255,255,255);
			$colour = imagecolorallocate($image,0,0,0);
			$border = imagecolorallocate($image,208,208,208);

			$x1 = 0; 
			$y1 = 0; 
			$x2 = ImageSX($image) - 1; 
			$y2 = ImageSY($image) - 1; 

			for($i = 0; $i < 2; $i++) { 
				ImageRectangle($image, $x1++, $y1++, $x2--, $y2--, $border); 
			}

			foreach($content_a as $l => $t) {
				$l = $l + 1;
				$x = 3;
				$y = ($l * $lsize) + 3 - $fsize;
				imagestring($image,1,$x,$y,$t,$colour);
				if(($l * $lsize) >= $height) {
					break;
				}
			}
			imagejpeg($image,dbunescbin($attach['content']) . '.thumb');
		}
	}
}