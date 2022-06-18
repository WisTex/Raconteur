<?php

namespace Code\Daemon;

use Code\Lib\ServiceClass;    
use Code\Lib\Libzotdir;
use Code\Lib\Libzot;
use Code\Extend\Hook;
    
class Cron_daily
{

    public static function run($argc, $argv)
    {

        logger('cron_daily: start');

        /**
         * Cron Daily
         *
         */


        // make sure our own site record is up to date
        Libzot::import_site(Libzot::site_info(true));


        // Fire off the Cron_weekly process if it's the correct day.

        $d3 = intval(datetime_convert('UTC', 'UTC', 'now', 'N'));
        if ($d3 == 7) {
            Run::Summon([ 'Cron_weekly' ]);
        }

        // once daily run birthday_updates and then expire in background

        // FIXME: add birthday updates, both locally and for xprof for use
        // by directory servers

        update_birthdays();

        // expire any read notifications over a month old

        q(
            "delete from notify where seen = 1 and created < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('60 DAY')
        );

        // expire any unread notifications over a year old

        q(
            "delete from notify where seen = 0 and created < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('1 YEAR')
        );

        // expire old delivery reports

        $keep_reports = intval(get_config('system', 'expire_delivery_reports'));
        if ($keep_reports === 0) {
            $keep_reports = 10;
        }

        q(
            "delete from dreport where dreport_time < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval($keep_reports . ' DAY')
        );

        // delete accounts that did not submit email verification within 3 days

        $r = q(
            "select * from register where password = 'verify' and created < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('3 DAY')
        );
        if ($r) {
            foreach ($r as $rv) {
                q(
                    "DELETE FROM account WHERE account_id = %d",
                    intval($rv['uid'])
                );

                q(
                    "DELETE FROM register WHERE id = %d",
                    intval($rv['id'])
                );
            }
        }

        // expire any expired accounts
        ServiceClass::downgrade_accounts();

        Run::Summon([ 'Expire' ]);

        remove_obsolete_hublocs();

        Hook::call('cron_daily', datetime_convert());

        set_config('system', 'last_expire_day', intval(datetime_convert('UTC', 'UTC', 'now', 'd')));

        /**
         * End Cron Daily
         */
    }
}
