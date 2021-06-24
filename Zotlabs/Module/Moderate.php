<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libsync;
use Zotlabs\Daemon\Run;

require_once('include/conversation.php');


class Moderate extends Controller {


	function get() {
		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		App::set_pager_itemspage(60);
		$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));               

		//show all items
		if(argc() == 1) {
			$r = q("select item.id as item_id, item.* from item where item.uid = %d and item_blocked = %d and item_deleted = 0 order by created desc $pager_sql",
				intval(local_channel()),
				intval(ITEM_MODERATED)
			);
			if(! $r) {
				info( t('No entries.') . EOL);
			}

		}

		// show a single item
		if(argc() == 2) {
			$post_id = escape_tags(argv(1));
			if(strpos($post_id,'b64.') === 0) {
				$post_id = @base64url_decode(substr($post_id,4));
			}
			$r = q("select item.id as item_id, item.* from item where item.mid = '%s' and item.uid = %d and item_blocked = %d and item_deleted = 0 order by created desc $pager_sql",
				dbesc($post_id),
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
					Libsync::build_sync_packet(local_channel(),array('item' => array(encode_item($sync_item[0],true))));
				}
				if($action === 'approve') {
					if ($item['id'] !== $item['parent']) {
						// if this is a group comment, call tag_deliver() to generate the associated
						// Announce activity so microblog destinations will see it in their home timeline
						$role = get_pconfig(local_channel(),'system','permissions_role');
						$rolesettings = PermissionRoles::role_perms($role);
						$channel_type = isset($rolesettings['channel_type']) ? $rolesettings['channel_type'] : 'normal';

						$is_group = (($channel_type === 'group') ? true : false);
						if ($is_group) {
							tag_deliver(local_channel(),$post_id);
						}
					}
					Run::Summon( [ 'Notifier', 'comment-new', $post_id ] );
				}
				goaway(z_root() . '/moderate');
			}
		}

		if($r) {
			xchan_query($r);
			$items = fetch_post_tags($r,true);
		}
		else {
			$items = [];
		}

		$o = conversation($items,'moderate',false,'traditional');
		$o .= alt_pager(count($items));
		return $o;

	}

}
