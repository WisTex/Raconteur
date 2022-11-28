<?php

namespace Code\Daemon;

use Code\Lib\ServiceClass;
use Code\Lib\Channel;
            
class Expire implements DaemonInterface
{

    public function run(int $argc, array $argv): void
    {
        cli_startup();

        // physically remove anything which has completed PHASE2 federated deletion

        q("delete from item where item_pending_remove = 1");

        // Perform final cleanup on previously deleted items where
        // DROPITEM_PHASE1 has completed and more than 4 days have elapsed
        // so notifications should have been delivered.

        $pending_deletes = q(
            "select id from item where item_deleted = 1 and item_pending_remove = 0 and changed < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('4 DAY')
        );
        if ($pending_deletes) {
            foreach ($pending_deletes as $item) {
                drop_item($item['id'], DROPITEM_PHASE2);
            }
        }

        logger('expire: start', LOGGER_DEBUG);

        $site_expire = intval(get_config('system', 'default_expire_days'));
        $commented_days = intval(get_config('system', 'active_expire_days'));

        logger('site_expire: ' . $site_expire);

        $channels = q("SELECT channel_id, channel_system, channel_address, channel_expire_days from channel where true");

        if ($channels) {
            foreach ($channels as $channel) {
                // expire the sys channel separately
                if (intval($channel['channel_system'])) {
                    continue;
                }

                // service class default (if non-zero) over-rides the site default

                $service_class_expire = ServiceClass::fetch($channel['channel_id'], 'expire_days');
                if (intval($service_class_expire)) {
                    $channel_expire = $service_class_expire;
                } else {
                    $channel_expire = $site_expire;
                }

                if (
                    intval($channel_expire) && (intval($channel_expire) < intval($channel['channel_expire_days'])) ||
                    intval($channel['channel_expire_days'] == 0)
                ) {
                    $expire_days = $channel_expire;
                } else {
                    $expire_days = $channel['channel_expire_days'];
                }

                // if the site or service class expiration is non-zero and less than person expiration, use that
                logger('Expire: ' . $channel['channel_address'] . ' interval: ' . $expire_days, LOGGER_DEBUG);
                item_expire($channel['channel_id'], $expire_days, $commented_days);
            }
        }

        $sys_channel = Channel::get_system();
        if ($sys_channel) {
            // this should probably just fetch the channel_expire_days from the sys channel,
            // but there's no convenient way to set it.

            $expire_days = intval(get_config('system', 'sys_expire_days', 30));

            if ($site_expire && $site_expire < $expire_days) {
                $expire_days = $site_expire;
            }
            logger('Expire: sys interval: ' . $expire_days, LOGGER_DEBUG);

            if ($expire_days) {
                item_expire($sys_channel['channel_id'], $expire_days, $commented_days);
            }

            logger('Expire: sys: done', LOGGER_DEBUG);
        }
    }
}
