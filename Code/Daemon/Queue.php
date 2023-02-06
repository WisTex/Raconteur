<?php

/** @file */

namespace Code\Daemon;

use Code\Lib as Zlib;

class Queue implements DaemonInterface
{

    public function run(int $argc, array $argv): void
    {
        $queue_id = ($argc > 1) ? $argv[1] : '';

        logger('queue: start');

        // delete all queue items more than 3 days old
        // but first mark these sites dead if we haven't heard from them in a month

        $oldqItems = q("select outq_posturl from outq where outq_created < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('3 DAY')
        );
        if ($oldqItems) {
            foreach ($oldqItems as $qItem) {
                $h = parse_url($qItem['outq_posturl']);
                $site_url = $h['scheme'] . '://' . $h['host'] . (($h['port']) ? ':' . $h['port'] : '');
                q("update site set site_dead = 1 where site_dead = 0 and site_url = '%s' and site_update < %s - INTERVAL %s",
                    dbesc($site_url),
                    db_utcnow(),
                    db_quoteinterval('1 MONTH')
                );
            }
        }

        logger('Removing ' . count($oldqItems) . ' old queue entries');
        q("DELETE FROM outq WHERE outq_created < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('3 DAY')
        );

        if ($queue_id) {
            $qItems = q("SELECT * FROM outq WHERE outq_hash = '%s' LIMIT 1",
                dbesc($queue_id)
            );
            logger('queue deliver: ' . $qItems[0]['outq_hash'] . ' to ' . $qItems[0]['outq_posturl'], LOGGER_DEBUG);
            Zlib\Queue::deliver(array_shift($qItems));
        }
        else {
            do {
                $qItems = q(
                    "SELECT * FROM outq WHERE outq_delivered = 0 and outq_scheduled < %s limit 1",
                    db_utcnow()
                );
                if ($qItems) {
                    logger('queue deliver: ' . $qItems[0]['outq_hash'] . ' to ' . $qItems[0]['outq_posturl'], LOGGER_DEBUG);
                    Zlib\Queue::deliver(array_shift($qItems));
                }
            } while ($qItems);
         }
    }
}
