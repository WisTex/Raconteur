<?php

namespace Code\Lib;

/**
 * @brief Site configuration storage is built on top of the under-utilised xconfig.
 *
 * @see XConfig
 */

class SConfig
{

    public static function Load($server_id)
    {
        return XConfig::Load('s_' . $server_id);
    }

    public static function Get($server_id, $family, $key, $default = false)
    {
        return XConfig::Get('s_' . $server_id, $family, $key, $default);
    }

    public static function Set($server_id, $family, $key, $value)
    {
        return XConfig::Set('s_' . $server_id, $family, $key, $value);
    }

    public static function Delete($server_id, $family, $key)
    {
        return XConfig::Delete('s_' . $server_id, $family, $key);
    }
}
