<?php

namespace Zotlabs\Lib;

class Api_router
{

    private static $routes = [];

    public static function register($path, $fn, $auth_required)
    {
        self::$routes[$path] = ['func' => $fn, 'auth' => $auth_required];
    }

    public static function find($path)
    {
        if (array_key_exists($path, self::$routes)) {
            return self::$routes[$path];
        }

        $with_params = dirname($path) . '/[id]';

        if (array_key_exists($with_params, self::$routes)) {
            return self::$routes[$with_params];
        }

        return null;
    }

    public static function dbg()
    {
        return self::$routes;
    }
}
