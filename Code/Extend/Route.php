<?php

namespace Code\Extend;

class Route
{

    public static function register($file, $modulename): void
    {
        $routes = self::get();
        $routes[] = [$file, $modulename];
        self::set($routes);
    }

    public static function unregister($file, $modulename): void
    {
        $routes = self::get();
        if ($routes) {
            $new_routes = [];
            foreach ($routes as $route) {
                if ($route[0] !== $file && $route[1] !== $modulename) {
                    $new_routes[] = $route;
                }
            }
            self::set($new_routes);
        }
    }

    public static function unregister_by_file($file): void
    {
        $routes = self::get();
        if ($routes) {
            $new_routes = [];
            foreach ($routes as $route) {
                if ($route[0] !== $file) {
                    $new_routes[] = $route;
                }
            }
            self::set($new_routes);
        }
    }

    public static function get()
    {
        return get_config('system', 'routes', []);
    }

    public static function set($value)
    {
        return set_config('system', 'routes', $value);
    }
}
