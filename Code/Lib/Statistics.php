<?php

namespace Code\Lib;

use Code\Lib\Config;

class Statistics {

    function get_channels_all()
    {
        $r = q(
            "select count(channel_id) as channels_total from channel left join account on account_id = channel_account_id
            where account_flags = 0 "
        );
        $total = ($r) ? intval($r[0]['channels_total']) : 0;
        Config::Set('system', 'channels_total_stat', $total);
        return $total;
    }

    function get_channels_6mo()
    {
        $r = q(
            "select channel_id from channel left join account on account_id = channel_account_id
            where account_flags = 0 and channel_active > %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('6 MONTH')
        );
        $total = ($r) ? count($r) : 0;
        Config::Set('system', 'channels_active_halfyear_stat', $total);
        return $total;
    }

    function get_channels_1mo()
    {
        $r = q(
            "select channel_id from channel left join account on account_id = channel_account_id
            where account_flags = 0 and channel_active > %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('1 MONTH')
        );
        $total = ($r) ? count($r) : 0;
        Config::Set('system', 'channels_active_monthly_stat', $total);
        return $total;
    }

    function get_posts()
    {
        $posts = q("SELECT COUNT(*) AS local_posts FROM item WHERE item_wall = 1 and id = parent");
        $total = ($posts) ? intval($posts[0]['local_posts']) : 0;
        Config::Set('system', 'local_posts_stat', $total);
        return $total;
    }

    function get_comments()
    {
        $posts = q("SELECT COUNT(*) AS local_posts FROM item WHERE item_wall = 1 and id != parent");
        $total = ($posts) ? intval($posts[0]['local_posts']) : 0;
        Config::Set('system', 'local_comments_stat', $total);
        return $total;
    }

}
