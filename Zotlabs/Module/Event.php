<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;

class Event extends Controller
{

    public function init()
    {

        if (ActivityStreams::is_as_request()) {
            $item_id = argv(1);

            if (!$item_id) {
                return;
            }

            $item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 
				and item.item_delayed = 0 and item.item_blocked = 0 ";

            $sql_extra = item_permissions_sql(0);

            $r = q(
                "select * from item where mid like '%s' $item_normal $sql_extra limit 1",
                dbesc(z_root() . '/activity/' . $item_id . '%')
            );

            if (!$r) {
                $r = q(
                    "select * from item where mid like '%s' $item_normal limit 1",
                    dbesc(z_root() . '/activity/' . $item_id . '%')
                );

                if ($r) {
                    http_status_exit(403, 'Forbidden');
                }
                http_status_exit(404, 'Not found');
            }

            xchan_query($r, true);
            $items = fetch_post_tags($r, true);

            $channel = channelx_by_n($items[0]['uid']);

            if (!is_array($items[0]['obj'])) {
                $obj = json_decode($items[0]['obj'], true);
            } else {
                $obj = $items[0]['obj'];
            }

            as_return_and_die($obj, $channel);
        }
    }
}
