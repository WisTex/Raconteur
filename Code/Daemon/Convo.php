<?php

namespace Code\Daemon;

use Code\Lib\Activity;
use Code\Lib\ActivityStreams;
use Code\Lib\ASCollection;
use Code\Lib\Channel;

class Convo
{

    public static function run($argc, $argv)
    {

        logger('convo invoked: ' . print_r($argv, true));

        if ($argc != 4) {
            killme();
        }

        $id = $argv[1];
        $channel_id = intval($argv[2]);
        $contact_hash = $argv[3];

        $channel = Channel::from_id($channel_id);
        if (! $channel) {
            killme();
        }

        $r = q(
            "SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash
			WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
            intval($channel_id),
            dbesc($contact_hash)
        );
        if (! $r) {
            killme();
        }

        $contact = array_shift($r);

        $obj = new ASCollection($id, $channel);

        $messages = $obj->get();

        if ($messages) {
            foreach ($messages as $message) {
                if (is_string($message)) {
                    $message = Activity::fetch($message, $channel);
                }
                // set client flag because comments will probably just be objects and not full blown activities
                // and that lets us use implied_create
                $AS = new ActivityStreams($message, null, true);
                if ($AS->is_valid() && is_array($AS->obj)) {
                    $item = Activity::decode_note($AS, true);
                    Activity::store($channel, $contact['abook_xchan'], $AS, $item, true, true);
                }
            }
        }
    }
}
