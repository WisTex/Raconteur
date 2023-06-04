<?php

namespace Code\Module;

/*
 * Pinned post processing
 */

use App;
use Code\Lib\Libsync;
use Code\Lib\PConfig;
use Code\Web\Controller;

class Pin extends Controller
{


    public function init()
    {

        if (argc() !== 1) {
            http_status_exit(400, 'Bad request');
        }

        if (!local_channel()) {
            http_status_exit(403, 'Forbidden');
        }
    }


    public function post()
    {

        $item_id = intval(str_replace('pin-', '', $_POST['id']));

        if ($item_id <= 0) {
            http_status_exit(404, 'Not found');
        }
        $channel = App::get_channel();

        $r = q(
            "SELECT * FROM item WHERE id = %d AND id = parent AND uid = %d AND owner_xchan = '%s' AND item_private = 0 LIMIT 1",
            intval($item_id),
            intval($channel['channel_id']),
            dbesc($channel['channel_hash'])
        );

        if (!$r) {
            notice(t('Unable to locate original post.'));
            http_status_exit(404, 'Not found');
        } else {
            $pinned = PConfig::Get($channel['channel_id'], 'pinned', $r[0]['item_type'], []);
            if (in_array($r[0]['mid'], $pinned)) {
                $narr = [];
                foreach ($pinned as $p) {
                    if ($p !== $r[0]['mid']) {
                        $narr[] = $p;
                    }
                }
            } else {
                $narr = $pinned;
                $narr[] = $r[0]['mid'];
            }
            PConfig::Set($channel['channel_id'], 'pinned', $r[0]['item_type'], $narr);
            Libsync::build_sync_packet($channel['channel_id']);
        }
    }
}
