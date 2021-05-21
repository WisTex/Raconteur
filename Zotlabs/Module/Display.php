<?php

namespace Zotlabs\Module;


use App;
use Zotlabs\Lib\System;
use Zotlabs\Web\Controller;

require_once("include/bbcode.php");
require_once('include/security.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');


class Display extends Controller {

	function get($update = 0, $load = false) {

		$noscript_content = (get_config('system', 'noscript_content', '1') && (! $update));

		$module_format = 'html';

		if(argc() > 1) {
			$module_format = substr(argv(1),strrpos(argv(1),'.') + 1);
			if(! in_array($module_format,['atom','zot','json']))
				$module_format = 'html';			
		}

		if($load)
			$_SESSION['loadtime_display'] = datetime_convert();
	
		if(observer_prohibited()) {
			notice( t('Public access denied.') . EOL);
			return;
		}
	
		if(argc() > 1) {
			$item_hash = argv(1);
			if($module_format !== 'html') {
				$item_hash = substr($item_hash,0,strrpos($item_hash,'.'));
			}
		}
	
		if($_REQUEST['mid'])
			$item_hash = $_REQUEST['mid'];

		if(! $item_hash) {
			App::$error = 404;
			notice( t('Item not found.') . EOL);
			return;
		}
	
		$observer_is_owner = false;
		$updateable = false;

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
				'acl'                 => populate_acl($channel_acl,true, \Zotlabs\Lib\PermissionDescription::fromGlobalPermission('view_stream'), get_post_aclDialogDescription(), 'acl_dialog_post'),	
				'permissions'         => $channel_acl,
				'bang'                => '',
				'visitor'             => true,
				'profile_uid'         => local_channel(),
				'return_path'         => 'channel/' . $channel['channel_address'],
				'expanded'            => true,
				'editor_autocomplete' => true,
				'bbco_autocomplete'   => 'bbcode',
				'bbcode'              => true,
				'jotnets'             => true,
				'reset'               => t('Reset form')
			);
	
			$o = '<div id="jot-popup">';
			$o .= status_editor($x);
			$o .= '</div>';
		}
	
		// This page can be viewed by anybody so the query could be complicated
		// First we'll see if there is a copy of the item which is owned by us - if we're logged in locally.
		// If that fails (or we aren't logged in locally), 
		// query an item in which the observer (if logged in remotely) has cid or gid rights
		// and if that fails, look for a copy of the post that has no privacy restrictions.  
		// If we find the post, but we don't find a copy that we're allowed to look at, this fact needs to be reported.
	
		// find a copy of the item somewhere
	
		$target_item = null;

		if(strpos($item_hash,'b64.') === 0)
			$decoded = @base64url_decode(substr($item_hash,4));
		if($decoded)
			$item_hash = $decoded;

		$r = q("select id, uid, mid, parent_mid, thr_parent, verb, item_type, item_deleted, author_xchan, item_blocked from item where mid like '%s' limit 1",
			dbesc($item_hash . '%')
		);
	
		if($r) {
			$target_item = $r[0];
		}

		$x = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($target_item['author_xchan'])
		);
		if($x) {
// not yet ready for prime time
//			\App::$poi = $x[0];
		}

		// if the item is to be moderated redirect to /moderate
		if($target_item['item_blocked'] == ITEM_MODERATED) {
			goaway(z_root() . '/moderate/' . $target_item['id']);
		}
	
		$r = null;
	
		if($target_item['item_type']  == ITEM_TYPE_WEBPAGE) {
			$x = q("select * from channel where channel_id = %d limit 1",
				intval($target_item['uid'])
			);
			$y = q("select * from iconfig left join item on iconfig.iid = item.id 
				where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'WEBPAGE' and item.id = %d limit 1",
				intval($target_item['uid']),
				intval($target_item['parent'])
			);
			if($x && $y) {
				goaway(z_root() . '/page/' . $x[0]['channel_address'] . '/' . $y[0]['v']);
			}
			else {
				notice( t('Page not found.') . EOL);
			 	return '';
			}
		}
		if($target_item['item_type']  == ITEM_TYPE_ARTICLE) {
			$x = q("select * from channel where channel_id = %d limit 1",
				intval($target_item['uid'])
			);
			$y = q("select * from iconfig left join item on iconfig.iid = item.id 
				where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'ARTICLE' and item.id = %d limit 1",
				intval($target_item['uid']),
				intval($target_item['parent'])
			);
			if($x && $y) {
				goaway(z_root() . '/articles/' . $x[0]['channel_address'] . '/' . $y[0]['v']);
			}
			else {
				notice( t('Page not found.') . EOL);
			 	return '';
			}
		}
		if($target_item['item_type']  == ITEM_TYPE_CARD) {
			$x = q("select * from channel where channel_id = %d limit 1",
				intval($target_item['uid'])
			);
			$y = q("select * from iconfig left join item on iconfig.iid = item.id 
				where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'CARD' and item.id = %d limit 1",
				intval($target_item['uid']),
				intval($target_item['parent'])
			);
			if($x && $y) {
				goaway(z_root() . '/cards/' . $x[0]['channel_address'] . '/' . $y[0]['v']);
			}
			else {
				notice( t('Page not found.') . EOL);
			 	return '';
			}
		}
		if ($target_item['item_type'] == ITEM_TYPE_CUSTOM) {
			call_hooks('item_custom_display',$target_item);
			notice( t('Page not found.') . EOL);
			return '';
		}

		
		
		$static = ((array_key_exists('static',$_REQUEST)) ? intval($_REQUEST['static']) : 0);
	
	
		$simple_update = (($update) ? " AND item_unseen = 1 " : '');
			
		if($update && $_SESSION['loadtime_display'])
			$simple_update = " AND item.changed > '" . datetime_convert('UTC','UTC',$_SESSION['loadtime_display']) . "' ";
		if($load)
			$simple_update = '';
	
		if($static && $simple_update)
			$simple_update .= " and item_thread_top = 0 and author_xchan = '" . protect_sprintf(get_observer_hash()) . "' ";
	
		if((! $update) && (! $load)) {

			$static  = ((local_channel()) ? channel_manual_conv_update(local_channel()) : 1);

			// if the target item is not a post (eg a like) we want to address its thread parent

			$mid = ((($target_item['verb'] == ACTIVITY_LIKE) || ($target_item['verb'] == ACTIVITY_DISLIKE)) ? $target_item['thr_parent'] : $target_item['mid']);

			// if we got a decoded hash we must encode it again before handing to javascript 
			if($decoded)
				$mid = 'b64.' . base64url_encode($mid);

			$o .= '<div id="live-display"></div>' . "\r\n";
			$o .= "<script> var profile_uid = " . ((intval(local_channel())) ? local_channel() : (-1))
				. "; var netargs = '?f='; var profile_page = " . App::$pager['page'] . "; </script>\r\n";
	
			App::$page['htmlhead'] .= replace_macros(get_markup_template("build_query.tpl"),array(
				'$baseurl' => z_root(),
				'$pgtype'  => 'display',
				'$uid'     => '0',
				'$gid'     => '0',
				'$cid'     => '0',
				'$cmin'    => '(-1)',
				'$cmax'    => '(-1)',
				'$star'    => '0',
				'$liked'   => '0',
				'$conv'    => '0',
				'$spam'    => '0',
				'$fh'      => '0',
				'$dm'      => '0',
				'$nouveau' => '0',
				'$wall'    => '0',
				'$draft'   => '0',
				'$static'  => $static,
				'$page'    => ((App::$pager['page'] != 1) ? App::$pager['page'] : 1),
				'$list'    => ((x($_REQUEST,'list')) ? intval($_REQUEST['list']) : 0),
				'$search'  => '',
				'$xchan'   => '',
				'$order'   => '',
				'$file'    => '',
				'$cats'    => '',
				'$tags'    => '',
				'$dend'    => '',
				'$dbegin'  => '',
				'$verb'    => '',
				'$net'     => '',
				'$mid'     => (($mid) ? urlencode($mid) : '')
			));

			head_add_link([ 
				'rel'   => 'alternate',
				'type'  => 'application/json+oembed',
				'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . \App::$query_string),
				'title' => 'oembed'
			]);

		}

		$observer_hash = get_observer_hash();
		$item_normal = item_normal();
		$item_normal_update = item_normal_update();

		$sql_extra = ((local_channel()) ? EMPTY_STR : item_permissions_sql(0, $observer_hash));

		if($noscript_content || $load) {

			$r = null;

			require_once('include/channel.php');
			$sys = get_sys_channel();
			$sysid = $sys['channel_id'];

			if(local_channel()) {
				$r = q("SELECT item.id as item_id from item WHERE uid = %d and mid = '%s' $item_normal limit 1",
					intval(local_channel()),
					dbesc($target_item['parent_mid'])
				);
				if($r) {
					$updateable = true;
				}
			}

			if (! (is_array($r) && count($r))) {
				$r = q("SELECT item.id as item_id from item WHERE mid = '%s' $sql_extra $item_normal limit 1",
					dbesc($target_item['parent_mid'])
				);
			}
		}
		elseif ($update && !$load) {
			$r = null;

			require_once('include/channel.php');
			$sys = get_sys_channel();
			$sysid = $sys['channel_id'];

			if (local_channel()) {
				$r = q("SELECT item.parent AS item_id from item WHERE uid = %d and parent_mid = '%s' $item_normal_update $simple_update limit 1",
					intval(local_channel()),
					dbesc($target_item['parent_mid'])
				);
				if($r) {
					$updateable = true;
				}
			}

			if(! $r) {
				$r = q("SELECT item.parent AS item_id from item WHERE parent_mid = '%s' $sql_extra $item_normal_update $simple_update limit 1",
					dbesc($target_item['parent_mid'])
				);
			}
			$_SESSION['loadtime_display'] = datetime_convert();
		}
	
		else {
			$r = [];
		}

		if($r) {
			$parents_str = ids_to_querystr($r,'item_id');
			if($parents_str) {
				$items = q("SELECT item.*, item.id AS item_id 
					FROM item
					WHERE parent in ( %s ) $item_normal $sql_extra ",
					dbesc($parents_str)
				);
				xchan_query($items);
				$items = fetch_post_tags($items,true);
				$items = conv_sort($items,'created');
			}
		}
		else {
			$items = [];
		}

		// see if the top-level post owner chose to block search engines
		
		if ($items && get_pconfig($items[0]['uid'],'system','noindex')) {
			App::$meta->set('robots', 'noindex, noarchive');
		}

		foreach ($items as $item) {
			if ($item['mid'] === $item_hash) {


				if(preg_match("/\[[zi]mg(.*?)\]([^\[]+)/is", $items[0]['body'], $matches)) {
					$ogimage = $matches[2];
					//	Will we use og:image:type someday? We keep this just in case
					//	$ogimagetype = guess_image_type($ogimage);
				}

				// some work on post content to generate a description
				// almost fully based on work done on Hubzilla by Max Kostikov
				$ogdesc = $item['body'];

				$ogdesc = bbcode($ogdesc, [ 'export' => true ]);
				$ogdesc = trim(html2plain($ogdesc, 0, true));
				$ogdesc = html_entity_decode($ogdesc, ENT_QUOTES, 'UTF-8');

				// remove all URLs
				$ogdesc = preg_replace("/https?\:\/\/[a-zA-Z0-9\:\/\-\?\&\;\.\=\_\~\#\%\$\!\+\,\@]+/", "", $ogdesc);

				// shorten description
				$ogdesc = substr($ogdesc, 0, 300);
				$ogdesc = str_replace("\n", " ", $ogdesc);
				while (strpos($ogdesc, "  ") !== false)
					$ogdesc = str_replace("  ", " ", $ogdesc);
				$ogdesc = (strlen($ogdesc) < 298 ? $ogdesc : rtrim(substr($ogdesc, 0, strrpos($ogdesc, " ")), "?.,:;!-") . "...");

				$ogsite = (System::get_site_name()) ? escape_tags(System::get_site_name()) : System::get_platform_name();

				// we can now start loading content
				if ($item['mid'] == $item['parent_mid']) {
					App::$meta->set('og:title', ($items[0]['title']
						? sprintf( t('"%1$s", shared by %2$s with %3$s'),$items[0]['title'],$item['author']['xchan_name'],$ogsite)
						: sprintf( t('%1$s shared this post with %2$s'),$item['author']['xchan_name'],$ogsite)));
                                        App::$meta->set('og:image', (isset($ogimage) ? $ogimage : System::get_site_icon()));
                                        App::$meta->set('og:type', 'article');
                                        App::$meta->set('og:url:secure_url', $item['llink']);
					App::$meta->set('og:description', ($ogdesc ? $ogdesc : sprintf( t('Not much to read, click to see the post.'))));
                                }
				else {
	                                if (($target_item['verb'] == ACTIVITY_LIKE) || ($target_item['verb'] == ACTIVITY_DISLIKE)) {
	                                        App::$meta->set('og:title', ($items[0]['title'] 
							? sprintf( t('%1$s shared a reaction to "%2$s"'),$item['author']['xchan_name'],$items[0]['title'])
							: sprintf( t('%s shared a reaction to this post/conversation'),$item['author']['xchan_name'])));
	                                        App::$meta->set('og:image', (isset($ogimage) ? $ogimage : System::get_site_icon()));
						App::$meta->set('og:type', 'article');
						App::$meta->set('og:url:secure_url', $item['llink']);
	                                        App::$meta->set('og:description', $ogdesc);
					}
					else {
                                                App::$meta->set('og:title', ($items[0]['title'] 
                                                        ? sprintf( t('%1$s commented "%2$s"'),$item['author']['xchan_name'],$items[0]['title'])
                                                        : sprintf( t('%s shared a comment of this post/conversation'),$item['author']['xchan_name'])));
                                                App::$meta->set('og:image', (isset($ogimage) ? $ogimage : System::get_site_icon()));
                                                App::$meta->set('og:type', 'article');
                                                App::$meta->set('og:url:secure_url', $item['llink']);
                                                App::$meta->set('og:description', sprintf( t('%1$s wrote this: "%2$s"'),$item['author']['xchan_name'],$ogdesc));
                                        }
				}
			}
		} 

		switch($module_format) {
			
		case 'html':

			if ($update) {
				$o .= conversation($items, 'display', $update, 'client');
			}
			else {
				$o .= '<noscript>';
				if($noscript_content) {
					$o .= conversation($items, 'display', $update, 'traditional');
				}
				else {
					$o .= '<div class="section-content-warning-wrapper">' . t('You must enable javascript for your browser to be able to view this content.') . '</div>';
				}
				$o .= '</noscript>';

				App::$page['title'] = (($items[0]['title']) ? $items[0]['title'] . " - " . App::$page['title'] : App::$page['title']);

				$o .= conversation($items, 'display', $update, 'client');
			} 

			break;

		case 'atom':

			$atom = replace_macros(get_markup_template('atom_feed.tpl'), array(
				'$version'       => xmlify(System::get_project_version()),
				'$generator'     => xmlify(System::get_platform_name()),
				'$generator_uri' => 'https://hubzilla.org',
				'$feed_id'       => xmlify(App::$cmd),
				'$feed_title'    => xmlify(t('Article')),
				'$feed_updated'  => xmlify(datetime_convert('UTC', 'UTC', 'now', ATOM_TIME)),
				'$author'        => '',
				'$owner'         => '',
				'$profile_page'  => xmlify(z_root() . '/display/' . $target_item['mid']),
			));
				
			$x = [ 'xml' => $atom, 'channel' => $channel, 'observer_hash' => $observer_hash, 'params' => $params ];
			call_hooks('atom_feed_top',$x);

			$atom = $x['xml'];

			// a much simpler interface
			call_hooks('atom_feed', $atom);


			if($items) {
				$type = 'html';
				foreach($items as $item) {
					if($item['item_private'])
						continue;
					$atom .= atom_entry($item, $type, null, '', true, '', false);
				}
			}

			call_hooks('atom_feed_end', $atom);

			$atom .= '</feed>' . "\r\n";

			header('Content-type: application/atom+xml');
			echo $atom;
			killme();
			
		}
	
		if($updateable) {
			$x = q("UPDATE item SET item_unseen = 0 where item_unseen = 1 AND uid = %d and parent = %d ",
				intval(local_channel()),
				intval($r[0]['item_id'])
			);
		}

		$o .= '<div id="content-complete"></div>';

		if((($update && $load) || $noscript_content) && (! $items)) {
			
			$r = q("SELECT id, item_deleted FROM item WHERE mid = '%s' LIMIT 1",
				dbesc($item_hash)
			);

			if($r) {
				if(intval($r[0]['item_deleted'])) {
					notice( t('Item has been removed.') . EOL );
				}
				else {	
					notice( t('Permission denied.') . EOL ); 
				}
			}
			else {
				notice( t('Item not found.') . EOL );
			}
	
		}

		return $o;

	}

}
