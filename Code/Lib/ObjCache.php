<?php

namespace Code\Lib;

class ObjCache
{
    public static function Get($path)
    {
        if (!$path) {
            return '';
        }
        $localpath = Hashpath::path($path, 'cache/as', 2);
        if (file_exists($localpath)) {
            return file_get_contents($localpath);
        }
        return '';
    }
    public static function Set($path,$content) {
        if (!$path) {
            return;
        }
        $localpath = Hashpath::path($path, 'cache/as', 2);
        file_put_contents($localpath, $content);
    }

    public static function Delete($path) {
        if (!$path) {
            return;
        }
        $localpath = Hashpath::path($path, 'cache/as', 2);
        if (file_exists($localpath)) {
            unlink($localpath);
        }
    }
}