<?php
namespace Zotlabs\Module;

require_once('include/conversation.php');
require_once('include/acl_selectors.php');


class Pubstream extends \Zotlabs\Web\Controller {

	function get($update = 0, $load = false) {

		if($load)
			$_SESSION['loadtime'] = datetime_convert();

		if((observer_prohibited(true))) {
			return login();
		}

		if(! intval(get_config('system','open_pubstream',1))) {
			if(! get_observer_hash()) {
				return login();
			}
		}

		$site_firehose = ((intval(get_config('system','site_firehose',0))) ? true : false);
		$net_firehose  = ((get_config('system','disable_discover_tab',1)) ? false : true);

		if(! ($site_firehose || $net_firehose)) {
			return '';
		}

		if($net_firehose) {
			$site_firehose = false;
		}

		$mid = ((x($_REQUEST,'mid')) ? $_REQUEST['mid'] : '');
		$hashtags   = ((x($_REQUEST,'tag')) ? $_REQUEST['tag'] : '');


		if(strpos($mid,'b64.') === 0)
			$decoded = @base64url_decode(substr($mid,4));
		if($decoded)
			$mid = $decoded;

		$item_normal = item_normal();
		$item_normal_update = item_normal_update();

		$static = ((array_key_exists('static',$_REQUEST)) ? intval($_REQUEST['static']) : 0);
		$net    = ((array_key_exists('net',$_REQUEST))    ? escape_tags($_REQUEST['net']) : '');


		if(local_channel() && (! $update)) {
	
			$channel = \App::get_channel();

			$channel_acl = array(
				'allow_cid' => $channel['channel_allow_cid'], 
				'allow_gid' => $channel['channel_allow_gid'], 
				'deny_cid'  => $channel['channel_deny_cid'], 
				'deny_gid'  => $channel['channel_deny_gid']
			); 

			$x = array(
				'is_owner'            => true,
				'allow_location'      => ((intval(get_pconfig($channel['channel_id'],'system','use_browser_location'))) ? '1' : ''),
				'default_location'    => $channel['channel_location'],
				'nickname'            => $channel['channel_address'],
				'lockstate'           => (($group || $cid || $channel['channel_allow_cid'] || $channel['channel_allow_gid'] || $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
	
				'acl'                 => populate_acl($channel_acl),
				'permissions'         => $channel_acl,
				'bang'                => '',
				'visitor'             => true,
				'profile_uid'         => local_channel(),
				'return_path'         => 'channel/' . $channel['channel_address'],
				'expanded'            => true,
				'editor_autocomplete' => true,
				'bbco_autocomplete'   => 'bbcode',
				'bbcode'              => true,
				'jotnets'             => true
			);
	
			$o = '<div id="jot-popup">';
			$o .= status_editor($a,$x);
			$o .= '</div>';
		}





	
		if(! $update && !$load) {

			nav_set_selected(t('Public Stream'));

			if(!$mid)
				$_SESSION['static_loadtime'] = datetime_convert();

			$static  = ((local_channel()) ? channel_manual_conv_update(local_channel()) : 1);
	
			$maxheight = get_config('system','home_divmore_height');
			if(! $maxheight)
				$maxheight = 400;
	
			$o .= '<div id="live-pubstream"></div>' . "\r\n";
			$o .= "<script> var profile_uid = " . ((intval(local_channel())) ? local_channel() : (-1)) 
				. "; var profile_page = " . \App::$pager['page'] 
				. "; divmore_height = " . intval($maxheight) . "; </script>\r\n";
	
			//if we got a decoded hash we must encode it again before handing to javascript 
			if($decoded)
				$mid = 'b64.' . base64url_encode($mid);

			\App::$page['htmlhead'] .= replace_macros(get_markup_template("build_query.tpl"),array(
				'$baseurl' => z_root(),
				'$pgtype'  => 'pubstream',
				'$uid'     => ((local_channel()) ? local_channel() : '0'),
				'$gid'     => '0',
				'$cid'     => '0',
				'$cmin'    => '0',
				'$cmax'    => '99',
				'$star'    => '0',
				'$liked'   => '0',
				'$conv'    => '0',
				'$spam'    => '0',
				'$fh'      => '1',
				'$nouveau' => '0',
				'$wall'    => '0',
				'$list'    => '0',
				'$static'  => $static,
				'$page'    => ((\App::$pager['page'] != 1) ? \App::$pager['page'] : 1),
				'$search'  => '',
				'$xchan'   => '',
				'$order'   => 'comment',
				'$file'    => '',
				'$cats'    => '',
				'$tags'    => $hashtags,
				'$dend'    => '',
				'$mid'     => $mid,
				'$verb'    => '',
				'$net'     => $net,
				'$dbegin'  => ''
			));
		}
	
		if($update && ! $load) {
			// only setup pagination on initial page view
			$pager_sql = '';
		}
		else {
			\App::set_pager_itemspage(20);
			$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(\App::$pager['itemspage']), intval(\App::$pager['start']));
		}
	
		require_once('include/channel.php');
		require_once('include/security.php');
	
		if($site_firehose) {
			$uids = " and item.uid in ( " . stream_perms_api_uids(PERMS_PUBLIC) . " ) and item_private = 0  and item_wall = 1 ";
		}
		else {
			$sys = get_sys_channel();
			$uids = " and item.uid  = " . intval($sys['channel_id']) . " ";
			$sql_extra = item_permissions_sql($sys['channel_id']);
			\App::$data['firehose'] = intval($sys['channel_id']);
		}
	
		if(get_config('system','public_list_mode'))
			$page_mode = 'list';
		else
			$page_mode = 'client';


		if(x($hashtags)) {
			$sql_extra .= protect_sprintf(term_query('item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));
		}

		$net_query = (($net) ? " left join xchan on xchan_hash = author_xchan " : ''); 
		$net_query2 = (($net) ? " and xchan_network = '" . protect_sprintf(dbesc($net)) . "' " : '');

		$abook_uids = " and abook.abook_channel = " . intval(\App::$profile['profile_uid']) . " ";
	
		$simple_update = (($_SESSION['loadtime']) ? " AND item.changed > '" . datetime_convert('UTC','UTC',$_SESSION['loadtime']) . "' " : '');
	
		if($load)
			$simple_update = '';

		if($static && $simple_update)
			$simple_update .= " and author_xchan = '" . protect_sprintf(get_observer_hash()) . "' ";

		//logger('update: ' . $update . ' load: ' . $load);

		if($update) {
	
			$ordering = "commented";
	
			if($load) {
				if($mid) {
					$r = q("SELECT parent AS item_id FROM item
						left join abook on item.author_xchan = abook.abook_xchan 
						$net_query
						WHERE mid like '%s' $uids $item_normal
						and (abook.abook_blocked = 0 or abook.abook_flags is null)
						$sql_extra3 $sql_extra $sql_nets $net_query2 LIMIT 1",
						dbesc($mid . '%')
					);
				}
				else {
					// Fetch a page full of parent items for this page
					$r = q("SELECT item.id AS item_id FROM item 
						left join abook on ( item.author_xchan = abook.abook_xchan $abook_uids )
						$net_query
						WHERE true $uids and item.item_thread_top = 1 $item_normal
						and (abook.abook_blocked = 0 or abook.abook_flags is null)
						$sql_extra3 $sql_extra $sql_nets $net_query2
						ORDER BY $ordering DESC $pager_sql "
					);
				}
			}
			elseif($update) {
				if($mid) {
					$r = q("SELECT parent AS item_id FROM item
						left join abook on item.author_xchan = abook.abook_xchan
						$net_query
						WHERE mid like '%s' $uids $item_normal_update $simple_update
						and (abook.abook_blocked = 0 or abook.abook_flags is null)
						$sql_extra3 $sql_extra $sql_nets $net_query2 LIMIT 1",
						dbesc($mid . '%')
					);
				}
				else {
					$r = q("SELECT parent AS item_id FROM item
						left join abook on item.author_xchan = abook.abook_xchan
						$net_query
						WHERE true $uids $item_normal_update
						$simple_update
						and (abook.abook_blocked = 0 or abook.abook_flags is null)
						$sql_extra3 $sql_extra $sql_nets $net_query2"
					);
				}
				$_SESSION['loadtime'] = datetime_convert();
			}

			// Then fetch all the children of the parents that are on this page
			$parents_str = '';
			$update_unseen = '';
	
			if($r) {
	
				$parents_str = ids_to_querystr($r,'item_id');
	
				$items = q("SELECT item.*, item.id AS item_id FROM item
					WHERE true $uids $item_normal
					AND item.parent IN ( %s )
					$sql_extra ",
					dbesc($parents_str)
				);
	
				// use effective_uid param of xchan_query to help sort out comment permission
				// for sys_channel owned items. 

				xchan_query($items,true,(($sys) ? local_channel() : 0));
				$items = fetch_post_tags($items,true);
				$items = conv_sort($items,$ordering);
			}
			else {
				$items = array();
			}
	
		}
	
		// fake it
		$mode = ('pubstream');
	
		$o .= conversation($items,$mode,$update,$page_mode);

		if($mid)
			$o .= '<div id="content-complete"></div>';
	
		if(($items) && (! $update))
			$o .= alt_pager(count($items));

		return $o;
	
	}
}
