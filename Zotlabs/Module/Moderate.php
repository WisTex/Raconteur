<?php

namespace Zotlabs\Module;

require_once('include/conversation.php');


class Moderate extends \Zotlabs\Web\Controller {


	function get() {
		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		\App::set_pager_itemspage(60);
		$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(\App::$pager['itemspage']), intval(\App::$pager['start']));               

		//show all items
		if(argc() == 1) {
			$r = q("select item.id as item_id, item.* from item where item.uid = %d and item_blocked = %d and item_deleted = 0 order by created desc $pager_sql",
				intval(local_channel()),
				intval(ITEM_MODERATED)
			);
		}

		//show a single item
		if(argc() == 2) {
			$post_id = intval(argv(1));

			$r = q("select item.id as item_id, item.* from item where item.id = %d and item.uid = %d and item_blocked = %d and item_deleted = 0 order by created desc $pager_sql",
				intval($post_id),
				intval(local_channel()),
				intval(ITEM_MODERATED)
			);
		}

		if(argc() > 2) {
			$post_id = intval(argv(1));
			if(! $post_id)
				goaway(z_root() . '/moderate');

			$action = argv(2);

			$r = q("select * from item where uid = %d and id = %d and item_blocked = %d limit 1",
				intval(local_channel()),
				intval($post_id),
				intval(ITEM_MODERATED)
			);

			if($r) {
				$item = $r[0];

				if($action === 'approve') {
					q("update item set item_blocked = 0 where uid = %d and id = %d",
						intval(local_channel()),
						intval($post_id)
					);

					$item['item_blocked'] = 0;

					item_update_parent_commented($item);

					notice( t('Comment approved') . EOL);
				}
				elseif($action === 'drop') {
					drop_item($post_id,false);
					notice( t('Comment deleted') . EOL);
				} 

				// refetch the item after changes have been made
			
				$r = q("select * from item where id = %d",
					intval($post_id)
				);
				if($r) {
					xchan_query($r);
					$sync_item = fetch_post_tags($r);
					build_sync_packet(local_channel(),array('item' => array(encode_item($sync_item[0],true))));
				}
				if($action === 'approve') {
					\Zotlabs\Daemon\Master::Summon(array('Notifier', 'comment-new', $post_id));
				}
				goaway(z_root() . '/moderate');
			}
		}

		if($r) {
			xchan_query($r);
			$items = fetch_post_tags($r,true);
		}
		else {
			$items = array();
		}

		$o = conversation($items,'moderate',false,'traditional');
		$o .= alt_pager(count($items));
		return $o;

	}

}
