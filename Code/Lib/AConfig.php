<?php

namespace Code\Lib;

// account configuration storage is built on top of the under-utilised xconfig

class AConfig
{

    public static function Load($account_id)
    {
        return XConfig::Load('a_' . $account_id);
    }

    public static function Get($account_id, $family, $key, $default = false)
    {
        return XConfig::Get('a_' . $account_id, $family, $key, $default);
    }

    public static function Set($account_id, $family, $key, $value)
    {
        return XConfig::Set('a_' . $account_id, $family, $key, $value);
    }

    public static function Delete($account_id, $family, $key)
    {
        return XConfig::Delete('a_' . $account_id, $family, $key);
    }
}
