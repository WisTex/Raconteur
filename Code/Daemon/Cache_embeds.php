<?php

namespace Code\Daemon;

class Cache_embeds
{

    /**
     * @param $argc
     * @param $argv
     * @return void
     */
    public static function run($argc, $argv)
    {

        if (! $argc == 2) {
            return;
        }

        $c = q(
            "select body, html, created from item where id = %d ",
            dbesc(intval($argv[1]))
        );

        if (! $c) {
            return;
        }

        $item = array_shift($c);

        $cache_expire = intval(get_config('system', 'default_expire_days'));
        if ($cache_expire <= 0) {
            $cache_expire = 60;
        }
        $cache_enable = !(($cache_expire) && ($item['created'] < datetime_convert('UTC', 'UTC', 'now - ' . $cache_expire . ' days')));

        $s = bbcode($item['body']);
        $s = sslify($s, $cache_enable);
    }
}
