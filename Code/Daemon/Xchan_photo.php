<?php

/** @file */

namespace Code\Daemon;

class Xchan_photo
{

    public static function run($argc, $argv)
    {

        if ($argc != 3) {
            return;
        }

        $url   = hex2bin($argv[1]);
        $xchan = hex2bin($argv[2]);

        // Some photo sources hang after connect and aren't caught by curl timeout

        set_time_limit(90);

        $photos = import_remote_xchan_photo($url, $xchan);
        if ($photos) {
            $r = q(
                "update xchan set xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s'",
                dbescdate(datetime_convert()),
                dbesc($photos[0]),
                dbesc($photos[1]),
                dbesc($photos[2]),
                dbesc($photos[3]),
                dbesc($xchan)
            );
        }

        return;
    }
}
