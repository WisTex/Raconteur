<?php

namespace Code\Lib;

use Code\Daemon\Run;

class Img_cache
{

    public static int $cache_life = 18600 * 7;

    public static function get_filename($url, $prefix = '.'): string
    {
        return Hashpath::path($url, $prefix);
    }

    // Check to see if we have this url in our cache
    // If we have it return true.
    // If we do not, or the cache file is empty or expired, return false
    // but attempt to fetch the entry in the background

    public static function check($url, $prefix = '.'): bool
    {

        if (str_contains($url, z_root())) {
            return false;
        }

        $path = self::get_filename($url, $prefix);
        if (file_exists($path)) {
            $t = filemtime($path);
            if ($t && time() - $t >= self::$cache_life) {
                Run::Summon(['Cache_image', $url, $path]);
                return false;
            } else {
                return (bool)filesize($path);
            }
        }

        // Cache_image invokes url_to_cache() as a background task

        Run::Summon(['Cache_image', $url, $path]);
        return false;
    }

    public static function url_to_cache($url, $file): bool
    {

        $fp = fopen($file, 'wb');

        if (!$fp) {
            logger('failed to open storage file: ' . $file, LOGGER_NORMAL, LOG_ERR);
            return false;
        }

        // don't check certs, and since we're running in the background,
        // allow a two-minute timeout rather than the default one minute.
        // This is a compromise. We want to cache all the slow sites we can,
        // but don't want to rack up too many processes doing so.

        $x = Url::get($url, ['filep' => $fp, 'novalidate' => true, 'timeout' => 120]);

        fclose($fp);

        if ($x['success'] && file_exists($file)) {
            $i = @getimagesize($file);
            if ($i && $i[2]) {  // looking for non-zero imagetype
                Run::Summon(['CacheThumb', basename($file)]);
                return true;
            }
        }

        // We could not cache the image for some reason. Leave an empty file here
        // to provide a record of the attempt. We'll use this as a flag to avoid
        // doing it again repeatedly.

        file_put_contents($file, EMPTY_STR);
        logger('cache failed from  ' . $url);
        return false;
    }
}
