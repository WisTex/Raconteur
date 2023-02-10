<?php

namespace Code\Thumbs;

use wapmorgan\Mp3Info\Mp3Info;

class Mp3audio
{

    public function Match($type)
    {
        return (($type === 'audio/mpeg') ? true : false);
    }

    public function Thumb($attach, $preview_style, $height = 300, $width = 300)
    {

        $audio = new Mp3Info($attach['content'], true);
        if (! $audio->hasCover) {
            return;
        }

        $photo = $audio->getCover();

        if ($photo) {
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
