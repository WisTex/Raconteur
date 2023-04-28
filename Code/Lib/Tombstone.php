<?php

namespace Code\Lib;


class Tombstone
{
    public static function check($url,$channel_id)
    {
        $r = q("select id from tombstone where id_hash = '%s' and id_channel = %d",
            dbesc(hash('sha256',$url)),
            intval($channel_id)
        );
        return (bool) $r;
    }

    public static function store($url, $channel_id)
    {
        $r = q("insert into tombstone (id_hash, id_channel, deleted_at) values ('%s', %d, '%s')",
            dbesc(hash('sha256',$url)),
            intval($channel_id),
            dbesc(datetime_convert())
        );
        return $r;
    }

}
