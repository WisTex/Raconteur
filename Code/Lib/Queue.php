<?php

/** @file */

namespace Code\Lib;

use Code\Web\HTTPSig;
use Code\Lib\Activity;
use Code\Lib\ActivityStreams;
use Code\Lib\Channel;
use Code\Lib\Libzot;
use Code\Lib\Url;
use Code\Nomad\Receiver;
use Code\Nomad\NomadHandler;
use Code\Extend\Hook;

class Queue
{

    public static function update($id, $msg, $add_priority = 0)
    {

        logger('queue: requeue item ' . $id, LOGGER_DEBUG);

        // Log the current error message to the current delivery.
        q("update outq set outq_log = CONCAT(outq_log, '%s') where outq_hash = '%s'",
            dbesc(datetime_convert() . EOL . $msg . EOL),
            dbesc($id)
        );

        // This queue item failed. Perhaps it was rejected. Perhaps the site is dead.
        // Since we don't really know, check and see if we've got something else destined
        // for that server and give it priority. At a minimum it will keep the queue from
        // getting stuck on a particular message when another one with different content
        // might actually succeed.

        // Ensure we fetch a record with the same driver type as the original.

        $x = q(
            "select outq_created, outq_hash, outq_posturl, outq_driver from outq where outq_hash = '%s' limit 1",
            dbesc($id)
        );
        if (!$x) {
            return;
        }

        $g = q(
            "select outq_created, outq_hash, outq_posturl, outq_driver from outq where outq_posturl = '%s' and outq_driver = '%s' and outq_hash != '%s' limit 1",
            dbesc($x[0]['outq_posturl']),
            dbesc($x[0]['outq_driver']),
            dbesc($id)
        );

        // swap them

        if ($g) {
            $x = $g;
        }


        $y = q(
            "select min(outq_created) as earliest from outq where outq_posturl = '%s' and outq_driver = '%s'",
            dbesc($x[0]['outq_posturl']),
            dbesc($x[0]['outq_driver'])
        );

        // look for the oldest queue entry with this destination URL. If it's older than a couple of days,
        // the destination is considered to be down and only scheduled once an hour, regardless of the
        // age of the current queue item.

        $might_be_down = false;

        if ($y) {
            $might_be_down = ((datetime_convert('UTC', 'UTC', $y[0]['earliest']) < datetime_convert('UTC', 'UTC', 'now - 2 days')) ? true : false);
        }


        // Set all other records for this destination way into the future.
        // The queue delivers by destination. We'll keep one queue item for
        // this destination (this one) with a shorter delivery. If we succeed
        // once, we'll try to deliver everything for that destination.
        // The delivery will be set to at most once per hour, and if the
        // queue item is less than 12 hours old, we'll schedule for fifteen
        // minutes.

        $r = q(
            "UPDATE outq SET outq_scheduled = '%s' WHERE outq_posturl = '%s' and outq_driver = '%s'",
            dbesc(datetime_convert('UTC', 'UTC', 'now + 5 days')),
            dbesc($x[0]['outq_posturl']),
            dbesc($x[0]['outq_driver'])
        );

        $since = datetime_convert('UTC', 'UTC', $y[0]['earliest']);

        if (($might_be_down) || ($since < datetime_convert('UTC', 'UTC', 'now - 12 hour'))) {
            $next = datetime_convert('UTC', 'UTC', 'now + 1 hour');
        } else {
            $next = datetime_convert('UTC', 'UTC', 'now + ' . (($add_priority) ? intval($add_priority) : 5) . ' minutes');
        }

        q(
            "UPDATE outq SET outq_updated = '%s',
            outq_priority = outq_priority + %d,
            outq_scheduled = '%s'
            WHERE outq_hash = '%s'",
            dbesc(datetime_convert()),
            intval($add_priority),
            dbesc($next),
            dbesc($x[0]['outq_hash'])
        );
    }


    public static function remove($id, $channel_id = 0)
    {
        logger('queue: remove queue item ' . $id, LOGGER_DEBUG);
        $sql_extra = (($channel_id) ? " and outq_channel = " . intval($channel_id) . " " : '');

        // figure out what endpoint it is going to.
        $record = q("select outq_posturl from outq where outq_hash = '%s' $sql_extra",
            dbesc($id)
        );

        if ($record) {
            q("DELETE FROM outq WHERE outq_hash = '%s' $sql_extra",
                dbesc($id)
            );

            // If there's anything remaining in the queue for this site, move one of them to the next active
            // queue run by setting outq_scheduled back to the present. We may be attempting to deliver it
            // as a 'piled_up' delivery, but this ensures the site has an active queue entry as long as queued
            // entries still exist for it. This fixes an issue where one immediate delivery left everything
            // else for that site undeliverable since all the other entries had been pushed far into the future.

            $forThisUrl = q("select * from outq where outq_posturl = '%s' limit 1",
                dbesc($record[0]['outq_posturl'])
            );

            if ($forThisUrl) {
                q("update outq set outq_scheduled = '%s' where outq_hash = '%s' and outq_delivered = 0",
                    dbesc(datetime_convert()),
                    dbesc($forThisUrl[0]['outq_hash'])
                );
            }
        }
        return;
    }

    public static function remove_by_posturl($posturl)
    {
        logger('queue: remove queue posturl ' . $posturl, LOGGER_DEBUG);

        q(
            "DELETE FROM outq WHERE outq_posturl = '%s' ",
            dbesc($posturl)
        );
    }


    public static function set_delivered($id, $channel_id = 0)
    {
        logger('queue: set delivered ' . $id, LOGGER_DEBUG);
        $sql_extra = (($channel_id) ? " and outq_channel = " . intval($channel_id) . " " : '');

        // Set the next scheduled run date so far in the future that it will be expired
        // long before it ever makes it back into the delivery chain.

        q(
            "update outq set outq_delivered = 1, outq_updated = '%s', outq_scheduled = '%s' where outq_hash = '%s' $sql_extra ",
            dbesc(datetime_convert()),
            dbesc(datetime_convert('UTC', 'UTC', 'now + 5 days')),
            dbesc($id)
        );
    }


    public static function insert($arr)
    {

        logger('insert: ' . print_r($arr, true), LOGGER_DATA);

        // do not queue anything with no destination

        if (!(array_key_exists('posturl', $arr) && trim($arr['posturl']))) {
            logger('no destination');
            return false;
        }


        $x = q(
            "insert into outq ( outq_hash, outq_account, outq_channel, outq_driver, outq_posturl, outq_async, outq_priority,
            outq_created, outq_updated, outq_scheduled, outq_notify, outq_msg, outq_log )
            values ( '%s', %d, %d, '%s', '%s', %d, %d, '%s', '%s', '%s', '%s', '%s', '' )",
            dbesc($arr['hash']),
            intval($arr['account_id']),
            intval($arr['channel_id']),
            dbesc((isset($arr['driver']) && $arr['driver']) ? $arr['driver'] : 'nomad'),
            dbesc($arr['posturl']),
            intval(1),
            intval((isset($arr['priority'])) ? $arr['priority'] : 0),
            dbesc(datetime_convert()),
            dbesc(datetime_convert()),
            dbesc((isset($arr['scheduled'])) ? $arr['scheduled'] : datetime_convert()),
            dbesc($arr['notify']),
            dbesc(($arr['msg']) ? $arr['msg'] : '')
        );
        return $x;
    }


    public static function deliver($outq, $immediate = false)
    {

        $base = null;
        $h = parse_url($outq['outq_posturl']);
        if ($h !== false) {
            $base = $h['scheme'] . '://' . $h['host'] . ((isset($h['port']) && intval($h['port'])) ? ':' . $h['port'] : '');
        }

        if (($base) && ($base !== z_root()) && ($immediate)) {
            $y = q(
                "select site_update, site_dead from site where site_url = '%s' ",
                dbesc($base)
            );
            if ($y) {
                // Don't bother delivering if the site is dead.
                // And if we haven't heard from the site in over a month - let them through but 3 strikes you're out.
                if (intval($y[0]['site_dead']) || ($y[0]['site_update'] < datetime_convert('UTC', 'UTC', 'now - 1 month')
                    && $outq['outq_priority'] > 20 )) {
                    q(
                        "update dreport set dreport_result = '%s' where dreport_queue = '%s'",
                        dbesc('site dead'),
                        dbesc($outq['outq_hash'])
                    );

                    self::remove_by_posturl($outq['outq_posturl']);
                    logger('dead site ignored ' . $base);
                    return;
                }
            } else {
                // zot sites should all have a site record, unless they've been dead for as long as
                // your site has existed. Since we don't know for sure what these sites are,
                // call them unknown

                site_store_lowlevel(
                    [
                        'site_url' => $base,
                        'site_update' => datetime_convert(),
                        'site_dead' => 0,
                        'site_type' => ((in_array($outq['outq_driver'], ['post', 'activitypub'])) ? SITE_TYPE_NOTZOT : SITE_TYPE_UNKNOWN),
                        'site_crypto' => ''
                    ]
                );
            }
        }

        $arr = array('outq' => $outq, 'base' => $base, 'handled' => false, 'immediate' => $immediate);
        Hook::call('queue_deliver', $arr);
        if ($arr['handled']) {
            return;
        }

        // "post" queue driver - used for diaspora and friendica-over-diaspora communications.

        if ($outq['outq_driver'] === 'post') {
            $result = Url::post($outq['outq_posturl'], $outq['outq_msg']);
            if ($result['success'] && $result['return_code'] < 300) {
                logger('deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
                if ($base) {
                    q(
                        "update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
                        dbesc(datetime_convert()),
                        dbesc($base)
                    );
                }
                q(
                    "update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
                    dbesc('accepted for delivery'),
                    dbesc(datetime_convert()),
                    dbesc($outq['outq_hash'])
                );
                self::remove($outq['outq_hash']);

                // server is responding - see if anything else is going to this destination and is piled up
                // and try to send some more. We're relying on the fact that do_delivery() results in an
                // immediate delivery otherwise we could get into a queue loop.

                if (!$immediate) {
                    $x = q(
                        "select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
                        dbesc($outq['outq_posturl'])
                    );

                    $piled_up = [];
                    if ($x) {
                        foreach ($x as $xx) {
                            $piled_up[] = $xx['outq_hash'];
                        }
                    }
                    if ($piled_up) {
                        // call do_delivery() with the force flag
                        do_delivery($piled_up, true);
                    }
                }
            } else {
                logger('deliver: queue post returned ' . $result['return_code']
                    . ' from ' . $outq['outq_posturl'], LOGGER_DEBUG);
                unset($result['body']); // Just record the header and errors, ignore the body.
                self::update($outq['outq_hash'], print_r($result, true), 10);
            }
            return;
        }

        if ($outq['outq_driver'] === 'asfetch') {
            $channel = Channel::from_id($outq['outq_channel']);
            if (!$channel) {
                logger('missing channel: ' . $outq['outq_channel']);
                return;
            }

            if (!ActivityStreams::is_url($outq['outq_posturl'])) {
                logger('fetch item is not url: ' . $outq['outq_posturl']);
                self::remove($outq['outq_hash']);
                return;
            }

            $j = Activity::fetch($outq['outq_posturl'], $channel);
            if ($j) {
                $AS = new ActivityStreams($j, null, true);
                if ($AS->is_valid() && isset($AS->data['type'])) {
                    if (ActivityStreams::is_an_actor($AS->data['type'])) {
                        Activity::actor_store($AS->data['id'], $AS->data);
                    }
                    if (strpos($AS->data['type'], 'Collection') !== false) {
                        // we are probably fetching a collection already - and do not support collection recursion at this time
                        self::remove($outq['outq_hash']);
                        return;
                    }
                    $item = Activity::decode_note($AS, true);
                    if ($item) {
                        Activity::store($channel, $channel['channnel_hash'], $AS, $item, true, true);
                    }
                }
                logger('deliver: queue fetch success from ' . $outq['outq_posturl'], LOGGER_DEBUG);
                self::remove($outq['outq_hash']);

                // server is responding - see if anything else is going to this destination and is piled up
                // and try to send some more. We're relying on the fact that do_delivery() results in an
                // immediate delivery otherwise we could get into a queue loop.

                if (!$immediate) {
                    $x = q(
                        "select outq_hash from outq where outq_driver = 'asfetch' and outq_channel = %d and outq_delivered = 0",
                        dbesc($outq['outq_channel'])
                    );

                    $piled_up = [];
                    if ($x) {
                        foreach ($x as $xx) {
                            $piled_up[] = $xx['outq_hash'];
                        }
                    }
                    if ($piled_up) {
                        do_delivery($piled_up, true);
                    }
                }
            } else {
                logger('deliver: queue fetch failed' . ' from ' . $outq['outq_posturl'], LOGGER_DEBUG);
                self::update($outq['outq_hash'], 'fetch failed', 10);
            }
            return;
        }

        if ($outq['outq_driver'] === 'activitypub') {
            $channel = Channel::from_id($outq['outq_channel']);
            if (!$channel) {
                logger('missing channel: ' . $outq['outq_channel']);
                return;
            }

            $m = parse_url($outq['outq_posturl']);

            $headers = [];
            $headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
            $ret = $outq['outq_msg'];
            logger('ActivityPub send: ' . jindent($ret), LOGGER_DATA);
            $headers['Date'] = datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T');
            $headers['Digest'] = HTTPSig::generate_digest_header($ret);
            $headers['Host'] = $m['host'];
            $headers['(request-target)'] = 'post ' . get_request_string($outq['outq_posturl']);

            $xhead = HTTPSig::create_sig($headers, $channel['channel_prvkey'], Channel::keyId($channel));
            if (strpos($outq['outq_posturl'], 'http') !== 0) {
                logger('bad url: ' . $outq['outq_posturl']);
                self::remove($outq['outq_hash']);
            }

            $result = Url::post($outq['outq_posturl'], $outq['outq_msg'], ['headers' => $xhead]);

            if ($result['success'] && $result['return_code'] < 300) {
                logger('deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
                if ($base) {
                    q(
                        "update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
                        dbesc(datetime_convert()),
                        dbesc($base)
                    );
                }
                q(
                    "update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
                    dbesc('accepted for delivery'),
                    dbesc(datetime_convert()),
                    dbesc($outq['outq_hash'])
                );
                self::remove($outq['outq_hash']);

                // server is responding - see if anything else is going to this destination and is piled up
                // and try to send some more. We're relying on the fact that do_delivery() results in an
                // immediate delivery otherwise we could get into a queue loop.

                if (!$immediate) {
                    $x = q(
                        "select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
                        dbesc($outq['outq_posturl'])
                    );

                    $piled_up = [];
                    if ($x) {
                        foreach ($x as $xx) {
                            $piled_up[] = $xx['outq_hash'];
                        }
                    }
                    if ($piled_up) {
                        do_delivery($piled_up, true);
                    }
                }
            }
            elseif ($result['return_code'] >= 400 && $result['return_code'] < 500) {
                q(
                    "update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
                    dbesc('delivery rejected' . ' ' . $result['return_code']),
                    dbesc(datetime_convert()),
                    dbesc($outq['outq_hash'])
                );
                self::remove($outq['outq_hash']);

            }
            else {
                $dr = q(
                    "select * from dreport where dreport_queue = '%s'",
                    dbesc($outq['outq_hash'])
                );
                if ($dr) {
                    // update every queue entry going to this site with the most recent communication error
                    q(
                        "update dreport set dreport_log = '%s' where dreport_site = '%s'",
                        dbesc(Url::format_error($result)),
                        dbesc($dr[0]['dreport_site'])
                    );
                }
                unset($result['body']);
                self::update($outq['outq_hash'], print_r($result, true), 10);
            }

            logger('deliver: queue post returned ' . $result['return_code'] . ' from ' . $outq['outq_posturl'], LOGGER_DEBUG);
            return;
        }

        // normal zot delivery

        logger('deliver: dest: ' . $outq['outq_posturl'], LOGGER_DEBUG);


        if (in_array($outq['outq_posturl'], [z_root() . '/zot', z_root() . '/nomad'])) {
            // local delivery
            $zot = new Receiver(new NomadHandler(), $outq['outq_notify']);
            $result = $zot->run();
            logger('returned_json: ' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOGGER_DATA);
            logger('deliver: local zot delivery succeeded to ' . $outq['outq_posturl']);
            Libzot::process_response($outq['outq_posturl'], ['success' => true, 'body' => json_encode($result)], $outq);

            if (!$immediate) {
                $x = q(
                    "select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
                    dbesc($outq['outq_posturl'])
                );

                $piled_up = [];
                if ($x) {
                    foreach ($x as $xx) {
                        $piled_up[] = $xx['outq_hash'];
                    }
                }
                if ($piled_up) {
                    do_delivery($piled_up, true);
                }
            }
        } else {
            logger('remote');
            $channel = null;

            if ($outq['outq_channel']) {
                $channel = Channel::from_id($outq['outq_channel'], true);
            }

            $host_crypto = null;

            if ($channel && $base) {
                $h = q(
                    "select hubloc_sitekey, site_crypto from hubloc left join site on hubloc_url = site_url where site_url = '%s' and hubloc_network in ('zot6','nomad') and hubloc_deleted = 0 order by hubloc_id desc limit 1",
                    dbesc($base)
                );
                if ($h) {
                    $host_crypto = $h[0];
                }
            }

            $msg = $outq['outq_notify'];

            if ($outq['outq_driver'] === 'nomad') {
                $result = Libzot::nomad($outq['outq_posturl'],$msg,$channel,$host_crypto);
            }
            else {
                $result = Libzot::zot($outq['outq_posturl'],$msg,$channel,$host_crypto);
            }

            if ($result['success']) {
                logger('deliver: remote nomad/zot delivery succeeded to ' . $outq['outq_posturl']);
                Libzot::process_response($outq['outq_posturl'], $result, $outq);
            } else {
                $dr = q(
                    "select * from dreport where dreport_queue = '%s'",
                    dbesc($outq['outq_hash'])
                );

                // update every queue entry going to this site with the most recent communication error
                q(
                    "update dreport set dreport_log = '%s' where dreport_site = '%s'",
                    dbesc(Url::format_error($result)),
                    dbesc($dr[0]['dreport_site'])
                );

                logger('deliver: remote nomad/zot delivery failed to ' . $outq['outq_posturl']);
                logger('deliver: remote nomad/zot delivery fail data: ' . print_r($result, true), LOGGER_DATA);
                unset($result['body']);
                self::update($outq['outq_hash'], print_r($result, true), 10);
            }
        }
        return;
    }
}
