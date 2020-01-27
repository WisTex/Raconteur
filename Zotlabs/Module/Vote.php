<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Activity;
use Zotlabs\Daemon\Master;
use Zotlabs\Lib\Libsync;

class Vote extends Controller {

	function init() {

		$channel = App::get_channel();

		if (! $channel) {
			http_status_exit(403, 'Permission denied');
			killme();
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
			$obj = json_decode($fetch[0]['obj'],true);

		}
		else {
			http_status_exit(404, 'not found');
			killme();
		}

		$valid = false;
		
		if ($obj['oneOf']) {
			foreach($obj['oneOf'] as $selection) {
				logger('selection: ' . $selection);
				logger('response: ' . $response);
				if($selection['name'] && $selection['name'] === $response) {
					$valid = true;
				}
			}
		}

		$choices = [];
		if ($obj['anyOf']) {
			foreach ($obj['oneOf'] as $selection) {
				$choices[] = $selection['name'];
			}
			foreach ($response as $res) {
				if (! in_array($res,$choices)) {
					$valid = false;
					break;
				}
				$valid = true;
			}
		}

		if (! $valid) {
			http_status_exit(400,'Bad request');
		}

		if (! is_array($response)) {
			$response = [ $response ];
		}

		foreach ($response as $res) {

			$item = [];


			$item['aid'] = $channel['channel_account_id'];
			$item['uid'] = $channel['channel_id'];
			$item['item_origin'] = true;
			$item['parent'] = $fetch[0]['id'];
			$item['parent_mid'] = $fetch[0]['mid'];
			$item['uuid'] = new_uuid();
			$item['mid'] = z_root() . '/item/' . $item['uuid'];
			$item['verb'] = 'Answer';
			$item['title'] = $res;
			$item['author_xchan'] = $channel['channel_hash'];
			$item['owner_xchan'] = $fetch[0]['author_xchan'];
			
			$item['obj'] = $obj;
			$item['obj_type'] = 'Question';

			$x = item_store($item); 

			retain_item($fetch[0]['id']);

			if($x['success']) {
				$itemid = $x['item_id'];
				Master::Summon( [ 'Notifier', 'like', $itemid ] );
			}
		
			$r = q("select * from item where id = %d",
				intval($itemid)
			);
			if ($r) {
				xchan_query($r);
				$sync_item = fetch_post_tags($r);
				Libsync::build_sync_packet($channel['channel_id'], [ 'item' => [ encode_item($sync_item[0],true) ] ]);
			}
		}
		json_return_and_die([ 'success' => true ]);
	}
}








