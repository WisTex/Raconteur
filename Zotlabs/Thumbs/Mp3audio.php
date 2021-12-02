<?php

namespace Zotlabs\Thumbs;

require_once('library/php-id3/PhpId3/Id3TagsReader.php');
require_once('library/php-id3/PhpId3/BinaryFileReader.php');
require_once('library/php-id3/PhpId3/Id3Tags.php');

use PhpId3\Id3TagsReader;

class Mp3audio
{

    public function Match($type)
    {
        return (($type === 'audio/mpeg') ? true : false);
    }

    public function Thumb($attach, $preview_style, $height = 300, $width = 300)
    {

        $fh = @fopen(dbunescbin($attach['content']), 'rb');
        if ($fh === false) {
            return;
        }
        $id3 = new Id3TagsReader($fh);
        $id3->readAllTags();

        $image = $id3->getImage();
        if (is_array($image)) {
            $photo = $image[1];
        }

        fclose($fh);

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

