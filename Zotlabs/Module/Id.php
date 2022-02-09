<?php

namespace Zotlabs\Module;

/**
 *
 * Controller for responding to x-zot: protocol requests
 * x-zot:_jkfRG85nJ-714zn-LW_VbTFW8jSjGAhAydOcJzHxqHkvEHWG2E0RbA_pbch-h4R63RG1YJZifaNzgccoLa3MQ/453c1678-1a79-4af7-ab65-6b012f6cab77

 *
 */

use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\ThreadListener;
use Zotlabs\Lib\IConfig;
use Zotlabs\Lib\Enotify;
use Zotlabs\Lib\Channel;
use App;

require_once('include/attach.php');
require_once('include/bbcode.php');
require_once('include/security.php');


class Id extends Controller
{

    public function init()
    {

        if (Libzot::is_zot_request()) {
            $conversation = false;

            $request_portable_id = argv(1);
            if (argc() > 2) {
                $item_id = argv(2);
            }

            $portable_id = EMPTY_STR;

            $sigdata = HTTPSig::verify(EMPTY_STR);
            if ($sigdata['portable_id'] && $sigdata['header_valid']) {
                $portable_id = $sigdata['portable_id'];
            }

            $chan = Channel::from_hash($request_portable_id);

            if ($chan) {
                $channel_id = $chan['channel_id'];
                if (!$item_id) {
                    $handler = new Channel();
                    App::$argc = 2;
                    App::$argv[0] = 'channel';
                    App::$argv[1] = $chan['channel_address'];
                    $handler->init();
                }
            } else {
                http_status_exit(404, 'Not found');
            }


            $item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_blocked = 0 ";

            $sql_extra = item_permissions_sql(0);

            $r = q(
                "select * from item where uuid = '%s' $item_normal $sql_extra and uid = %d limit 1",
                dbesc($item_id),
                intval($channel_id)
            );
            if (!$r) {
                $r = q(
                    "select * from item where uuid = '%s' $item_normal and uid = %d limit 1",
                    dbesc($item_id),
                    intval($channel_id)
                );
                if ($r) {
                    http_status_exit(403, 'Forbidden');
                }
                http_status_exit(404, 'Not found');
            }

            if (!perm_is_allowed($chan['channel_id'], get_observer_hash(), 'view_stream')) {
                http_status_exit(403, 'Forbidden');
            }

            xchan_query($r, true);
            $items = fetch_post_tags($r, true);

            $i = Activity::encode_item($items[0], (get_config('system', 'activitypub', ACTIVITYPUB_ENABLED) ? true : false));

            if (!$i) {
                http_status_exit(404, 'Not found');
            }

            $x = array_merge(['@context' => [
                ACTIVITYSTREAMS_JSONLD_REV,
                'https://w3id.org/security/v1',
                Activity::ap_schema()
            ]], $i);

            $headers = [];
            $headers['Content-Type'] = 'application/x-nomad+json';
            $ret = json_encode($x, JSON_UNESCAPED_SLASHES);
            $headers['Digest'] = HTTPSig::generate_digest_header($ret);
            $headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];
            $h = HTTPSig::create_sig($headers, $chan['channel_prvkey'], Channel::url($chan));
            HTTPSig::set_headers($h);
            echo $ret;
            killme();
        }
    }
}
