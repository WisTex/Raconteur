<?php

namespace Code\Lib;

class ThreadListener
{

    public static function isEnabled()
    {
        return Config::Get('system','enable_thread_listener');
    }

    public static function store($target_id, $portable_id, $ltype = 0)
    {
        if (!self::isEnabled()) {
            return true;
        }
        $x = self::fetch($target_id, $portable_id, $ltype);
        if (! $x) {
            return q(
                "insert into listeners ( target_id, portable_id, ltype ) values ( '%s', '%s' , %d ) ",
                dbesc($target_id),
                dbesc($portable_id),
                intval($ltype)
            );
        }
        return true;
    }

    public static function fetch($target_id, $portable_id, $ltype = 0)
    {
        if (! self::isEnabled()) {
            return false;
        }
        $x = q(
            "select * from listeners where target_id = '%s' and portable_id = '%s' and ltype = %d limit 1",
            dbesc($target_id),
            dbesc($portable_id),
            intval($ltype)
        );
        if ($x) {
            return $x[0];
        }
        return false;
    }

    public static function fetch_by_target($target_id, $ltype = 0)
    {
        if (! self::isEnabled()) {
            return [];
        }
        return q(
            "select * from listeners where target_id = '%s' and ltype = %d",
            dbesc($target_id),
            intval($ltype)
        );
    }

    public static function delete_by_target($target_id, $ltype = 0)
    {
        return q(
            "delete from listeners where target_id = '%s' and ltype = %d",
            dbesc($target_id),
            intval($ltype)
        );
    }

    public static function delete_by_pid($portable_id, $ltype = 0)
    {
        return q(
            "delete from listeners where portable_id = '%s' and ltype = %d",
            dbesc($portable_id),
            intval($ltype)
        );
    }
}
