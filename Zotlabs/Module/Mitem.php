<?php
namespace Zotlabs\Module;

require_once('include/menu.php');
require_once('include/acl_selectors.php');


class Mitem extends \Zotlabs\Web\Controller {

	function init() {

		if(argc() > 1 && argv(1) === 'sys' && is_site_admin()) {
			$sys = get_sys_channel();
			if($sys && intval($sys['channel_id'])) {
				\App::$is_sys = true;
			}
		}

		if(argc() > 1)
			$which = argv(1);
		else
			return;

		profile_load($which);
	
		if(argc() < 3)
			return;
	
		$m = menu_fetch_id(intval(argv(2)),\App::$profile['channel_id']);
		if(! $m) {
			notice( t('Menu not found.') . EOL);
			return '';
		}
		\App::$data['menu'] = $m;
	
	}
	
	function post() {
	
		if(! \App::$profile) {
			return;
		}

		$which = argv(1);


		$uid = \App::$profile['channel_id'];
	
		if(array_key_exists('sys', $_REQUEST) && $_REQUEST['sys'] && is_site_admin()) {
			$sys = get_sys_channel();
			$uid = intval($sys['channel_id']);
			\App::$is_sys = true;
		}
	
		if(! $uid)
			return;


		if(! \App::$data['menu'])
			return;
	
		if(!$_REQUEST['mitem_desc'] || !$_REQUEST['mitem_link']) {
			notice( t('Unable to create element.') . EOL);
			return;
		}
	
		$_REQUEST['mitem_channel_id'] = $uid;
		$_REQUEST['menu_id'] = \App::$data['menu']['menu_id'];
	
		$_REQUEST['mitem_flags'] = 0;
		if($_REQUEST['usezid'])
			$_REQUEST['mitem_flags'] |= MENU_ITEM_ZID;
		if($_REQUEST['newwin'])
			$_REQUEST['mitem_flags'] |= MENU_ITEM_NEWWIN;
	
		
		$mitem_id = ((argc() > 3) ? intval(argv(3)) : 0);
		if($mitem_id) {
			$_REQUEST['mitem_id'] = $mitem_id;
			$r = menu_edit_item($_REQUEST['menu_id'],$uid,$_REQUEST);	
			if($r) {
				menu_sync_packet($uid,get_observer_hash(),$_REQUEST['menu_id']);
				//info( t('Menu element updated.') . EOL);
				goaway(z_root() . '/mitem/' . $which . '/' . $_REQUEST['menu_id'] . ((\App::$is_sys) ? '?f=&sys=1' : ''));
			}
			else
				notice( t('Unable to update menu element.') . EOL);
	
		}
		else {
			$r = menu_add_item($_REQUEST['menu_id'],$uid,$_REQUEST);	
			if($r) {
				menu_sync_packet($uid,get_observer_hash(),$_REQUEST['menu_id']);
				//info( t('Menu element added.') . EOL);
				if($_REQUEST['submit']) {
					goaway(z_root() . '/menu/' . $which . ((\App::$is_sys) ? '?f=&sys=1' : ''));
				}
				if($_REQUEST['submit-more']) {
					goaway(z_root() . '/mitem/' . $which . '/' . $_REQUEST['menu_id'] . '?f=&display=block' . ((\App::$is_sys) ? '&sys=1' : '') );
				}
			}
			else
				notice( t('Unable to add menu element.') . EOL);
	
		}
	
	}
	
	
	function get() {
	
		$uid = local_channel();
		$owner = \App::$profile['channel_id'];
		$channel = channelx_by_n($owner);
		$observer = \App::get_observer();

		$which = argv(1);

		$ob_hash = (($observer) ? $observer['xchan_hash'] : '');
	
		if(\App::$is_sys && is_site_admin()) {
			$sys = get_sys_channel();
			$uid = intval($sys['channel_id']);
			$channel = $sys;
			$ob_hash = $sys['xchan_hash'];
		}
	
		if(! $uid) {
			notice( t('Permission denied.') . EOL);
			return '';
		}
	
		if(argc() < 3 || (! \App::$data['menu'])) {
			notice( t('Not found.') . EOL);
			return '';
		}
	
		$m = menu_fetch(\App::$data['menu']['menu_name'],$owner,$ob_hash);
		\App::$data['menu_item'] = $m;
	
		$menu_list = menu_list($owner);
	
		foreach($menu_list as $menus) {
			if($menus['menu_name'] != $m['menu']['menu_name'])
				$menu_names[] = $menus['menu_name'];
		}
	
		$acl = new \Zotlabs\Access\AccessList($channel);
	
		$lockstate = (($channel['channel_allow_cid'] || $channel['channel_allow_gid'] || $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock');
	
		if(argc() == 3) {
			$r = q("select * from menu_item where mitem_menu_id = %d and mitem_channel_id = %d order by mitem_order asc, mitem_desc asc",
				intval(\App::$data['menu']['menu_id']),
				intval($owner)
			);
	
			if($_GET['display']) {
				$display = $_GET['display'];
			}
			else {
				$display = (($r) ? 'none' : 'block');
			}

			$create = replace_macros(get_markup_template('mitemedit.tpl'), array(
				'$menu_id'     => \App::$data['menu']['menu_id'],
				'$permissions' => t('Menu Item Permissions'),
				'$permdesc'    => t("\x28click to open/close\x29"),
				'$aclselect'   => populate_acl($acl->get(),false),
				'$allow_cid'   => acl2json($acl->get()['allow_cid']),
				'$allow_gid'   => acl2json($acl->get()['allow_gid']),
				'$deny_cid'    => acl2json($acl->get()['deny_cid']),
				'$deny_gid'    => acl2json($acl->get()['deny_gid']),
				'$mitem_desc'  => array('mitem_desc', t('Link Name'), '', 'Visible name of the link','*'),
				'$mitem_link'  => array('mitem_link', t('Link or Submenu Target'), '', t('Enter URL of the link or select a menu name to create a submenu'), '*', 'list="menu-names"'),
				'$usezid'      => array('usezid', t('Use magic-auth if available'), true, '', array(t('No'), t('Yes'))),
				'$newwin'      => array('newwin', t('Open link in new window'), false,'', array(t('No'), t('Yes'))),
				'$mitem_order' => array('mitem_order', t('Order in list'),'0',t('Higher numbers will sink to bottom of listing')),
				'$submit'      => t('Submit and finish'),
				'$submit_more' => t('Submit and continue'),
				'$display'     => $display,
				'$lockstate'   => $lockstate,
				'$menu_names'  => $menu_names,
				'$nick'        => $which,
				'$sys'         => \App::$is_sys
			));
	
			$o .= replace_macros(get_markup_template('mitemlist.tpl'),array(
				'$title'       => t('Menu:'),
				'$create'      => $create,
				'$nametitle'   => t('Link Name'),
				'$targettitle' => t('Link Target'),
				'$menuname'    => \App::$data['menu']['menu_name'],
				'$menudesc'    => \App::$data['menu']['menu_desc'],
				'$edmenu'      => t('Edit menu'),
				'$menu_id'     => \App::$data['menu']['menu_id'],
				'$mlist'       => $r,
				'$edit'        => t('Edit element'),
				'$drop'        => t('Drop element'),
				'$new'         => t('New element'),
				'$hintmenu'    => t('Edit this menu container'),
				'$hintnew'     => t('Add menu element'),
				'$hintdrop'    => t('Delete this menu item'),
				'$hintedit'    => t('Edit this menu item'),
				'$nick'        => $which,
			));
	
			return $o;
		}
	
	
		if(argc() > 3) {

			if(intval(argv(3))) {
	
				$m = q("select * from menu_item where mitem_id = %d and mitem_channel_id = %d limit 1",
					intval(argv(3)),
					intval($owner)
				);
	
				if(! $m) {
					notice( t('Menu item not found.') . EOL);
					goaway(z_root() . '/menu/'. $which . ((\App::$is_sys) ? '?f=&sys=1' : ''));
				}
	
				$mitem = $m[0];
	
				$lockstate = (($mitem['allow_cid'] || $mitem['allow_gid'] || $mitem['deny_cid'] || $mitem['deny_gid']) ? 'lock' : 'unlock');
	
				if(argc() == 5 && argv(4) == 'drop') {
					menu_sync_packet($owner,get_observer_hash(),$mitem['mitem_menu_id']);
					$r = menu_del_item($mitem['mitem_menu_id'], $owner, intval(argv(3)));
					menu_sync_packet($owner,get_observer_hash(),$mitem['mitem_menu_id']);
					if($r)
						info( t('Menu item deleted.') . EOL);
					else
						notice( t('Menu item could not be deleted.'). EOL);
	
					goaway(z_root() . '/mitem/' . $which . '/' . $mitem['mitem_menu_id'] . ((\App::$is_sys) ? '?f=&sys=1' : ''));
				}
	
				// edit menu item
				$o = replace_macros(get_markup_template('mitemedit.tpl'), array(
					'$header' => t('Edit Menu Element'),
					'$menu_id' => \App::$data['menu']['menu_id'],
					'$permissions' => t('Menu Item Permissions'),
					'$permdesc' => t("\x28click to open/close\x29"),
					'$aclselect' => populate_acl($mitem,false),
					'$allow_cid' => acl2json($mitem['allow_cid']),
					'$allow_gid' => acl2json($mitem['allow_gid']),
					'$deny_cid' => acl2json($mitem['deny_cid']),
					'$deny_gid' => acl2json($mitem['deny_gid']),
					'$mitem_id' => intval(argv(3)),
					'$mitem_desc' => array('mitem_desc', t('Link text'), $mitem['mitem_desc'], '','*'),
					'$mitem_link'  => array('mitem_link', t('Link or Submenu Target'), $mitem['mitem_link'], 'Enter URL of the link or select a menu name to create a submenu', '*', 'list="menu-names"'),
					'$usezid' => array('usezid', t('Use magic-auth if available'), (($mitem['mitem_flags'] & MENU_ITEM_ZID) ? 1 : 0), '', array(t('No'), t('Yes'))),
					'$newwin' => array('newwin', t('Open link in new window'), (($mitem['mitem_flags'] & MENU_ITEM_NEWWIN) ? 1 : 0),'', array(t('No'), t('Yes'))),
					'$mitem_order' => array('mitem_order', t('Order in list'),$mitem['mitem_order'],t('Higher numbers will sink to bottom of listing')),
					'$submit' => t('Submit'),
					'$lockstate'     => $lockstate,
					'$menu_names'  => $menu_names,
					'$nick'  => $which
				));
	
				return $o;
			}
		}
	}
	
}
