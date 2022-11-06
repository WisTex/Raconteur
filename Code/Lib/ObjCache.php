<?php

namespace Code\Lib;

class ObjCache
{
    public static function Get($path)
    {
        $localpath = Hashpath::path($path, 'cache/as', 2);
        if (file_exists($localpath)) {
            return file_get_contents($localpath);
        }
        return '';
    }
    public static function Set($path,$content) {
        $localpath = Hashpath::path($path, 'cache/as', 2);
        file_put_contents($localpath, $content);
    }

    public static function Delete($path) {
        $localpath = Hashpath::path($path, 'cache/as', 2);
        if (file_exists($localpath)) {
            unlink($localpath);
        }
    }
}