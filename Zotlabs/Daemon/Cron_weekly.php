<?php

namespace Zotlabs\Daemon;

class Cron_weekly
{

    public static function run($argc, $argv)
    {

        /**
         * Cron Weekly
         *
         * Actions in the following block are executed once per day only on Sunday (once per week).
         *
         */

        call_hooks('cron_weekly', datetime_convert());

        z_check_cert();

        prune_hub_reinstalls();

        mark_orphan_hubsxchans();

        // Find channels that were removed in the last three weeks, but
        // haven't been finally cleaned up. These should be older than 10
        // days to ensure that "purgeall" messages have gone out or bounced
        // or timed out.

        $r = q(
            "select channel_id from channel where channel_removed = 1 and 
			channel_deleted >  %s - INTERVAL %s and channel_deleted < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('21 DAY'),
            db_utcnow(),
            db_quoteinterval('10 DAY')
        );
        if ($r) {
            foreach ($r as $rv) {
                channel_remove_final($rv['channel_id']);
            }
        }

        // get rid of really old poco records

        q(
            "delete from xlink where xlink_updated < %s - INTERVAL %s and xlink_static = 0 ",
            db_utcnow(),
            db_quoteinterval('14 DAY')
        );

        // Check for dead sites
        Run::Summon(['Checksites' ]);


        // clean up image cache - use site expiration or 60 days if not set or zero

        $files = glob('cache/img/*/*');
        $expire_days = intval(get_config('system', 'default_expire_days'));
        if ($expire_days <= 0) {
            $expire_days = 60;
        }
        $now = time();
        $maxage = 86400 * $expire_days;
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    if ($now - filemtime($file) >= $maxage) {
                        unlink($file);
                    }
                }
            }
        }

        // update searchable doc indexes

        Run::Summon([ 'Importdoc']);

        /**
         * End Cron Weekly
         */
    }
}
