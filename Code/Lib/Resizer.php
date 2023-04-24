<?php

namespace Code\Lib;

require_once('include/photos.php');

/**
 *  Performs initial thumbnail creation to a "sane size" using in this case, imagick convert via exec.
 *  This will get around PHP memory issues but is currently platform dependent.
 */

class Resizer
{
    const DEFAULT_MAX_SIZE = 1600;
    private string $converter_path;
    private array $getimagesize;

    public function __construct($converter_path,$getimagesize)
    {
        $this->converter_path = (file_exists($converter_path)) ? $converter_path : '';
        $this->getimagesize = $getimagesize;
    }

    private function constructDimension($max): bool|string
    {
        if (!isset($this->getimagesize)) {
            return false;
        }
        if ($this->getimagesize[0] > $max || $this->getimagesize[1] > $max) {
            return photo_calculate_scale(array_merge($this->getimagesize, ['max' => $max]));
        }
        return false;
    }

    public function resize($infile,$outfile,$max_size = self::DEFAULT_MAX_SIZE): bool
    {
        $dim = $this->constructDimension($max_size);
        if ($dim && $this->converter_path) {
            $cmd = $this->converter_path . ' ' . escapeshellarg($infile) . ' -resize ' . $dim . ' ' . escapeshellarg($outfile);
            exec($cmd);
            if (@file_exists($outfile)) {
                return true;
            }
        }
        return false;
    }
}

