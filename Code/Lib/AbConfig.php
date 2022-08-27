<?php

namespace Code\Lib;

class AbConfig
{

    public static function Load($channel_id, $xchan_hash, $family = ''): array|bool|null
    {
        $where = ($family) ? sprintf(" and cat = '%s' ", dbesc($family)) : '';
        return q(
            "select * from abconfig where chan = %d and xchan = '%s' $where",
            intval($channel_id),
            dbesc($xchan_hash)
        );
    }


    public static function Get($channel_id, $xchan_hash, $family, $key, $default = false)
    {
        $r = q(
            "select * from abconfig where chan = %d and xchan = '%s' and cat = '%s' and k = '%s' limit 1",
            intval($channel_id),
            dbesc($xchan_hash),
            dbesc($family),
            dbesc($key)
        );
        if ($r) {
            return unserialise($r[0]['v']);
        }
        return $default;
    }


    public static function Set($channel_id, $xchan_hash, $family, $key, $value)
    {

        $dbvalue = ((is_array($value))  ? serialise($value) : $value);
        $dbvalue = ((is_bool($dbvalue)) ? intval($dbvalue)  : $dbvalue);

        if (self::Get($channel_id, $xchan_hash, $family, $key) === false) {
            $r = q(
                "insert into abconfig ( chan, xchan, cat, k, v ) values ( %d, '%s', '%s', '%s', '%s' ) ",
                intval($channel_id),
                dbesc($xchan_hash),
                dbesc($family),
                dbesc($key),
                dbesc($dbvalue)
            );
        } else {
            $r = q(
                "update abconfig set v = '%s' where chan = %d and xchan = '%s' and cat = '%s' and k = '%s' ",
                dbesc($dbvalue),
                dbesc($channel_id),
                dbesc($xchan_hash),
                dbesc($family),
                dbesc($key)
            );
        }

        if ($r) {
            return $value;
        }
        return false;
    }


    public static function Delete($channel_id, $xchan_hash, $family, $key): bool
    {
        return q(
            "delete from abconfig where chan = %d and xchan = '%s' and cat = '%s' and k = '%s' ",
            intval($channel_id),
            dbesc($xchan_hash),
            dbesc($family),
            dbesc($key)
        );
    }
}
