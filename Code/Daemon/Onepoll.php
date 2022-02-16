<?php

/** @file */

namespace Code\Daemon;

use Code\Lib\Libzot;
use Code\Lib\ActivityStreams;
use Code\Lib\Activity;
use Code\Lib\ASCollection;
use Code\Lib\Socgraph;
    

class Onepoll
{

    public static function run($argc, $argv)
    {

        logger('onepoll: start');

        if (($argc > 1) && (intval($argv[1]))) {
            $contact_id = intval($argv[1]);
        }

        if (! $contact_id) {
            logger('onepoll: no contact');
            return;
        }

        $d = datetime_convert();

        $contacts = q(
            "SELECT abook.*, xchan.*, account.*
			FROM abook LEFT JOIN account on abook_account = account_id left join xchan on xchan_hash = abook_xchan 
			where abook_id = %d
			and abook_pending = 0 and abook_archived = 0 and abook_blocked = 0 and abook_ignored = 0
			AND (( account_flags = %d ) OR ( account_flags = %d )) limit 1",
            intval($contact_id),
            intval(ACCOUNT_OK),
            intval(ACCOUNT_UNVERIFIED)
        );

        if (! $contacts) {
            logger('onepoll: abook_id not found: ' . $contact_id);
            return;
        }

        $contact = array_shift($contacts);

        $t = $contact['abook_updated'];

        $importer_uid = $contact['abook_channel'];

        $r = q(
            "SELECT * from channel left join xchan on channel_hash = xchan_hash where channel_id = %d limit 1",
            intval($importer_uid)
        );

        if (! $r) {
            return;
        }

        $importer = $r[0];

        logger("onepoll: poll: ({$contact['id']}) IMPORTER: {$importer['xchan_name']}, CONTACT: {$contact['xchan_name']}");

        $last_update = ((($contact['abook_updated'] === $contact['abook_created']) || ($contact['abook_updated'] <= NULL_DATE))
            ? datetime_convert('UTC', 'UTC', 'now - 7 days')
            : datetime_convert('UTC', 'UTC', $contact['abook_updated'] . ' - 2 days')
        );

		if (in_array($contact['xchan_network'],['nomad']['zot6'])) {
            // update permissions

            $x = Libzot::refresh($contact, $importer);

            $responded = false;
            $updated   = datetime_convert();
            $connected = datetime_convert();
            if (! $x) {
                // mark for death by not updating abook_connected, this is caught in include/poller.php
                q(
                    "update abook set abook_updated = '%s' where abook_id = %d",
                    dbesc($updated),
                    intval($contact['abook_id'])
                );
            } else {
                q(
                    "update abook set abook_updated = '%s', abook_connected = '%s' where abook_id = %d",
                    dbesc($updated),
                    dbesc($connected),
                    intval($contact['abook_id'])
                );
                $responded = true;
            }

            if (! $responded) {
                return;
            }
        }

        $fetch_feed = true;

        // They haven't given us permission to see their stream

        $can_view_stream = intval(get_abconfig($importer_uid, $contact['abook_xchan'], 'their_perms', 'view_stream'));

        if (! $can_view_stream) {
            $fetch_feed = false;
        }

        // we haven't given them permission to send us their stream

        $can_send_stream = intval(get_abconfig($importer_uid, $contact['abook_xchan'], 'my_perms', 'send_stream'));

        if (! $can_send_stream) {
            $fetch_feed = false;
        }

        if ($contact['abook_created'] < datetime_convert('UTC', 'UTC', 'now - 1 week')) {
            $fetch_feed = false;
        }

        // In previous releases there was a mechanism to fetch 'external' or public stream posts from a site
        // (as opposed to a channel). This mechanism was deprecated as there is no reliable/scalable method
        // for informing downstream publishers when/if the content has expired or been deleted.
        // We can use the ThreadListener interface to implement this on the owner's outbox, however this is still a
        // work in progress and may present scaling issues. Making this work correctly with third-party fetches is
        // prohibitive as deletion requests would need to be relayed over potentially hostile networks.

        if ($fetch_feed) {
            $max = intval(get_config('system', 'max_imported_posts', 20));
            if (intval($max)) {
                $cl = get_xconfig($xchan, 'activitypub', 'collections');
                if (is_array($cl) && $cl) {
                    $url = ((array_key_exists('outbox', $cl)) ? $cl['outbox'] : '');
                    if ($url) {
                        logger('fetching outbox');
                        $url = $url . '?date_begin=' .  urlencode($last_update);
                        $obj = new ASCollection($url, $importer, 0, $max);
                        $messages = $obj->get();
                        if ($messages) {
                            foreach ($messages as $message) {
                                if (is_string($message)) {
                                    $message = Activity::fetch($message, $importer);
                                }
                                if (is_array($message)) {
                                    $AS = new ActivityStreams($message, null, true);
                                    if ($AS->is_valid() && is_array($AS->obj)) {
                                        $item = Activity::decode_note($AS, true);
                                        if ($item) {
                                            Activity::store($importer, $contact['abook_xchan'], $AS, $item, true, true);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // update the poco details for this connection

        $r = q(
            "SELECT xlink_id from xlink 
			where xlink_xchan = '%s' and xlink_updated > %s - INTERVAL %s and xlink_static = 0 limit 1",
            intval($contact['xchan_hash']),
            db_utcnow(),
            db_quoteinterval('7 DAY')
        );
        if (! $r) {
            Socgraph::poco_load($contact['xchan_hash'], $contact['xchan_connurl']);
        }
        return;
    }
}
