<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Activity;
use Zotlabs\Daemon\Run;
use Zotlabs\Lib\Libsync;

class Vote extends Controller
{

    public function init()
    {

        $ret = ['success' => false, 'message' => EMPTY_STR];

        $channel = App::get_channel();

        if (!$channel) {
            $ret['message'] = t('Permission denied.');
            json_return_and_die($ret);
        }


        $fetch = null;
        $id = argv(1);
        $response = $_REQUEST['answer'];

        if ($id) {
            $fetch = q("select * from item where id = %d limit 1",
                intval($id)
            );
        }

        if ($fetch && $fetch[0]['obj_type'] === 'Question') {
            $obj = json_decode($fetch[0]['obj'], true);

        } else {
            $ret['message'] = t('Poll not found.');
            json_return_and_die($ret);
        }

        $valid = false;

        if ($obj['oneOf']) {
            foreach ($obj['oneOf'] as $selection) {
                //		logger('selection: ' . $selection);
                //		logger('response: ' . $response);
                if ($selection['name'] && $selection['name'] === $response) {
                    $valid = true;
                }
            }
        }

        $choices = [];
        if ($obj['anyOf']) {
            foreach ($obj['anyOf'] as $selection) {
                $choices[] = $selection['name'];
            }
            foreach ($response as $res) {
                if (!in_array($res, $choices)) {
                    $valid = false;
                    break;
                }
                $valid = true;
            }
        }

        if (!$valid) {
            $ret['message'] = t('Invalid response.');
            json_return_and_die($ret);
        }

        if (!is_array($response)) {
            $response = [$response];
        }

        foreach ($response as $res) {

            $item = [];

            $item['aid'] = $channel['channel_account_id'];
            $item['uid'] = $channel['channel_id'];
            $item['item_origin'] = true;
            $item['parent'] = $fetch[0]['id'];
            $item['parent_mid'] = $fetch[0]['mid'];
            $item['thr_parent'] = $fetch[0]['mid'];
            $item['uuid'] = new_uuid();
            $item['mid'] = z_root() . '/item/' . $item['uuid'];
            $item['verb'] = 'Create';
            $item['title'] = $res;
            $item['author_xchan'] = $channel['channel_hash'];
            $item['owner_xchan'] = $fetch[0]['author_xchan'];
//			$item['allow_cid'] = '<' . $fetch[0]['author_xchan'] . '>';
//			$item['item_private'] = 1;

            // These two are placeholder values that will be reset after
            // we encode the item

            $item['obj_type'] = 'Note';
            $item['author'] = channelx_by_n($channel['channel_id']);

            $item['obj'] = Activity::encode_item($item, ((get_config('system', 'activitypub', ACTIVITYPUB_ENABLED)) ? true : false));

            // now reset the placeholders

            $item['obj_type'] = 'Answer';
            unset($item['author']);

            $x = item_store($item);

            retain_item($fetch[0]['id']);

            if ($x['success']) {
                $itemid = $x['item_id'];
                Run::Summon(['Notifier', 'like', $itemid]);
            }

            $r = q("select * from item where id = %d",
                intval($itemid)
            );
            if ($r) {
                xchan_query($r);
                $sync_item = fetch_post_tags($r);
                Libsync::build_sync_packet($channel['channel_id'], ['item' => [encode_item($sync_item[0], true)]]);
            }
        }
        $ret['success'] = true;
        $ret['message'] = t('Response submitted. Updates may not appear instantly.');
        json_return_and_die($ret);
    }
}








