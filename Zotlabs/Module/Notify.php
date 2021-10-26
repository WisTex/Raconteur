<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;


class Notify extends Controller {

	function init() {
		if (! local_channel()) {
			return;
		}

		$channel = App::get_channel();
		if (! $channel) {
			return;
		}

		if (argc() > 2 && argv(1) === 'view' && intval(argv(2))) {
			$r = q("select * from notify where id = %d and uid = %d limit 1",
				intval(argv(2)),
				intval(local_channel())
			);
			if ($r) {
				$x = [ 'channel_id' => local_channel(), 'update' => 'unset' ];
				call_hooks('update_unseen',$x);
				if ((! $_SESSION['sudo']) && ($x['update'] === 'unset' || intval($x['update']))) {
					q("update notify set seen = 1 where (( parent != '' and parent = '%s' and otype = '%s' ) or link = '%s' ) and uid = %d",
						dbesc($r[0]['parent']),
						dbesc($r[0]['otype']),
						dbesc($r[0]['link']),
						intval(local_channel())
					);
				}
				goaway($r[0]['link']);
			}
			notice( sprintf( t('A notification with that id was not found for channel \'%s\''), $channel['channel_name']));
			goaway(z_root());
		}
	
	
	}
	
	
	function get() {
		if (! local_channel()) {
			return login();
		}
	
		$notif_tpl = get_markup_template('notifications.tpl');
			
		$not_tpl = get_markup_template('notify.tpl');
	
		$r = q("SELECT * from notify where uid = %d and seen = 0 order by created desc",
			intval(local_channel())
		);
			
		if ($r) {
			foreach ($r as $it) {
				$notif_content .= replace_macros($not_tpl,array(
					'$item_link' => z_root().'/notify/view/'. $it['id'],
					'$item_image' => $it['photo'],
					'$item_text' => strip_tags(bbcode($it['msg'])),
					'$item_when' => relative_date($it['created'])
				));
			}
		} 
		else {
			$notif_content .= t('No more system notifications.');
		}
			
		$o .= replace_macros($notif_tpl,array(
			'$notif_header' => t('System Notifications'),
			'$tabs' => '', // $tabs,
			'$notif_content' => $notif_content,
		));
	
		return $o;
	
	}
}
