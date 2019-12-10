<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Activity;
use Zotlabs\Daemon\Master;



class React extends Controller {

	function get() {

		if(! local_channel())
			return;

		$sys = get_sys_channel();
		$channel = App::get_channel();

		$postid = $_REQUEST['postid'];

		if(! $postid)
			return;

		$emoji = $_REQUEST['emoji'];


		if($_REQUEST['emoji']) {

			$i = q("select * from item where id = %d and uid = %d",
				intval($postid),
				intval(local_channel())
			);

			if(! $i) {
				$i = q("select * from item where id = %d and uid = %d",
					intval($postid),
					intval($sys['channel_id'])
				);

				if($i) {
					$i = [ copy_of_pubitem($channel, $i[0]['mid']) ];
					$postid = (($i) ? $i[0]['id'] : 0);
				}
			}

			if(! $i) {
				return;
			}

			$item = array_shift($i);

			$n = [] ;
			$n['aid'] = $channel['channel_account_id'];
			$n['uid'] = $channel['channel_id'];
			$n['item_origin'] = true;
			$n['item_type'] = $item['item_type'];
			$n['parent'] = $postid;
			$n['parent_mid'] = $item['mid'];
			$n['uuid'] = new_uuid();
			$n['mid'] = z_root() . '/item/' . $n['uuid'];
			$n['verb'] = 'emojiReaction';
			$n['body'] = "\n\n" . '[img=32x32]' . z_root() . '/images/emoji/' . $emoji . '.png[/img]' . "\n\n";
			$n['author_xchan'] = $channel['channel_hash'];

			$n['obj'] = Activity::fetch_item( [ 'id' => $item['mid'] ] );
			$n['obj_type'] = ((array_path_exists('obj/type',$n)) ? $n['obj']['type'] : EMPTY_STR);

			$n['tgt_type'] = 'Image';
			
			$n['target'] = [
				'type' => 'Image',
				'name' => $emoji,
				'url'  => z_root() . '/images/emoji/' . $emoji . '.png'
			];
			
			$x = item_store($n); 

			retain_item($postid);

			if($x['success']) {
				$nid = $x['item_id'];
				Master::Summon( [ 'Notifier', 'like', $nid ] );
			}

		}

	}

}