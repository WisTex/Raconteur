<?php

namespace Zotlabs\Lib;

/*
 * Zotlabs\Lib\Hashpath
 *
 * Creates hashed directory structures for fast access and resistance to overloading any single directory with files.
 *
 * Takes a $url which could be any string
 * a $prefix which is where to place the hash directory in the filesystem, default is current directory
 * use an empty string for $prefix to place hash directories directly off the root directory
 * an optional $depth to indicate the hash level
 * $depth = 1, 256 directories, suitable for < 384K records/files
 * $depth = 2, 65536 directories, suitable for < 98M records/files
 * $depth = 3, 16777216 directories, suitable for < 2.5B records/files
 * ...
 * The total number of records anticipated divided by the number of hash directories should generally be kept to
 * less than 1500 entries for optimum performance though this varies by operating system and filesystem type.
 * ext4 uses 32 bit inode numbers (~4B record limit) so use caution or alternative filesystem types with $depth above 3.
 * an optional $mkdir (boolean) to recursively create the directory (ignoring errors) before returning
 *
 * examples: for a $url of 'abcdefg' and prefix of 'path' the following paths are returned for $depth = 1 and $depth = 3
 *    path/7d/7d1a54127b222502f5b79b5fb0803061152a44f92b37e23c6527baf665d4da9a
 *    path/7d/1a/54/7d1a54127b222502f5b79b5fb0803061152a44f92b37e23c6527baf665d4da9a
 *
 * see also: boot.php:os_mkdir() - here we provide the equivalent of mkdir -p with permissions of 770.
 *
 */

class Hashpath
{

    public static function path($url, $prefix = '.', $depth = 1, $mkdir = true)
    {
        $hash = hash('sha256', $url);
        $start = 0;
        $slice = 2;
        if ($depth < 1) {
            $depth = 1;
        }
        $sluglen = $depth * $slice;

        do {
            $slug = substr($hash, $start, $slice);
            $prefix .= '/' . $slug;
            $start += $slice;
            $sluglen -= $slice;
        } while ($sluglen);

        if ($mkdir) {
            os_mkdir($prefix, STORAGE_DEFAULT_PERMISSIONS, true);
        }

        return $prefix . '/' . $hash;
    }
}
